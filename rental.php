<?php
// rental.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Self-Drive Vehicle Rental";

// Enforce login
requireLogin();

$currentUser = getCurrentUser();
$db = getDB();

$selectedVehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$presetStartDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d');
$presetEndDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d', strtotime('+2 days'));

$rentalConfirmed = false;
$newRental = null;
$error = '';

// Handle POST Rental Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $pickupAddress = trim($_POST['pickup_address'] ?? '');
    $licenseNumber = trim($_POST['driving_license_number'] ?? '');

    if (!$vehicleId || empty($startDate) || empty($endDate) || empty($pickupAddress) || empty($licenseNumber)) {
        $error = "Please fill in all required fields.";
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $error = "End date cannot be earlier than start date.";
    } else {
        // Fetch vehicle details
        $stmtV = $db->prepare("SELECT * FROM vehicles WHERE id = ? AND status = 'available'");
        $stmtV->bind_param("i", $vehicleId);
        $stmtV->execute();
        $vehicle = $stmtV->get_result()->fetch_assoc();

        if (!$vehicle) {
            $error = "The selected vehicle is currently not available for rental.";
        } else {
            // Calculate days (inclusive)
            $startObj = new DateTime($startDate);
            $endObj = new DateTime($endDate);
            $interval = $startObj->diff($endObj);
            $totalDays = (int)$interval->days + 1;

            $pricePerDay = (float)$vehicle['price_per_day'];
            $totalAmount = $totalDays * $pricePerDay;

            // Generate Unique Rental Code e.g. RX-RENT-71932
            $rentalCode = "RX-RENT-" . rand(10000, 99999);

            $stmtInsert = $db->prepare("INSERT INTO rentals (rental_code, user_id, vehicle_id, start_date, end_date, total_days, price_per_day, total_amount, pickup_address, driving_license_number, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Approval', 'Pending')");

            $userId = (int)$currentUser['id'];
            $stmtInsert->bind_param("siissiddss", 
                $rentalCode, 
                $userId, 
                $vehicleId, 
                $startDate, 
                $endDate, 
                $totalDays, 
                $pricePerDay, 
                $totalAmount, 
                $pickupAddress, 
                $licenseNumber
            );

            if ($stmtInsert->execute()) {
                $rentalId = $stmtInsert->insert_id;
                // Do not mark the rental active or the vehicle rented until payment
                // has been verified by the Razorpay backend.

                $rentalConfirmed = true;
                $newRental = [
                    'code' => $rentalCode,
                    'vehicle' => $vehicle['name'],
                    'category' => $vehicle['category'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_days' => $totalDays,
                    'price_per_day' => $pricePerDay,
                    'total_amount' => $totalAmount,
                    'pickup_address' => $pickupAddress,
                    'license' => $licenseNumber
                ];
            } else {
                $error = "Database execution error: " . $db->error;
            }
        }
    }
}

// Fetch available vehicles
$vehicles = $db->query("SELECT id, name, category, brand, model_year, price_per_day, seating_capacity, fuel_type, transmission, image_url FROM vehicles WHERE status = 'available' ORDER BY category ASC, name ASC")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <?php if ($rentalConfirmed && $newRental): ?>
        <!-- Rental Confirmation Screen -->
        <div class="card" style="max-width: 650px; margin: 30px auto; text-align: center; padding: 40px 30px; border-top: 5px solid var(--secondary);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-size: 34px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fa-solid fa-key"></i>
            </div>
            <h1 style="font-size: 26px; font-weight: 800; color: var(--dark); margin-bottom: 6px;">Rental Booking Saved!</h1>
            <p style="color: var(--text-muted); margin-bottom: 24px;">Please complete the payment to activate your self-drive rental.</p>

            <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 20px; text-align: left; margin-bottom: 25px; border: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px;">
                    <span style="color: var(--text-muted); font-size: 14px;">Rental Code:</span>
                    <strong style="color: var(--secondary); font-size: 16px; font-family: monospace;"><?= $newRental['code'] ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Vehicle Reserved:</span>
                    <strong><?= htmlspecialchars($newRental['vehicle']) ?> (<?= htmlspecialchars($newRental['category']) ?>)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Rental Duration:</span>
                    <span><?= date('M d, Y', strtotime($newRental['start_date'])) ?> to <?= date('M d, Y', strtotime($newRental['end_date'])) ?> (<?= $newRental['total_days'] ?> Days)</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Pickup / Delivery Address:</span>
                    <span><?= htmlspecialchars($newRental['pickup_address']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Verified Driver License:</span>
                    <span style="font-family: monospace;"><?= htmlspecialchars($newRental['license']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px dashed var(--border); padding-top: 12px; margin-top: 10px; font-size: 18px; font-weight: 800; color: var(--dark);">
                    <span>Total Rental Amount:</span>
                    <span style="color: var(--accent);"><?= formatCurrency($newRental['total_amount']) ?></span>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <button id="rzp-button1" class="btn btn-primary" style="background-color: #2b8256; border-color: #2b8256;"><i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newRental['total_amount'], 2) ?> Now</button>
                <a href="dashboard.php#rentals" class="btn btn-outline">Pay Later / Manual</a>
            </div>
            <div id="rzp-error" style="color: red; margin-top: 15px; display: none;"></div>
        </div>

        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
        document.getElementById('rzp-button1').onclick = function(e) {
            e.preventDefault();
            const btn = this;
            const errorDiv = document.getElementById('rzp-error');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            errorDiv.style.display = 'none';

            // Create Order
            fetch('payment/create_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({booking_type: 'rental', booking_id: '<?= $rentalId ?>'})
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to create order');
                }

                var options = {
                    "key": data.key_id,
                    "amount": data.amount,
                    "currency": data.currency,
                    "name": "RideX",
                    "description": "Payment for Rental " + '<?= $newRental['code'] ?>',
                    "order_id": data.razorpay_order_id,
                    "handler": function (response) {
                        // Verify Payment
                        fetch('payment/verify_payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({razorpay_payment_id: response.razorpay_payment_id, razorpay_order_id: response.razorpay_order_id, razorpay_signature: response.razorpay_signature, booking_type: 'rental', booking_id: '<?= $rentalId ?>'})
                        })
                        .then(res => res.json())
                        .then(verifyData => {
                            if (verifyData.success) {
                                window.location.href = 'payment_success.php?type=rental&id=<?= $rentalId ?>';
                            } else {
                                errorDiv.innerText = verifyData.message || 'Payment verification failed';
                                errorDiv.style.display = 'block';
                            }
                        });
                    },
                    "prefill": {
                        "name": data.user_name,
                        "email": data.user_email,
                        "contact": data.user_phone
                    },
                    "theme": {
                        "color": "#0284c7"
                    }
                };
                var rzp1 = new Razorpay(options);
                rzp1.on('payment.failed', function (response){
                    errorDiv.innerText = response.error.description;
                    errorDiv.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newRental['total_amount'], 2) ?> Now';
                });
                rzp1.open();
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newRental['total_amount'], 2) ?> Now';
            })
            .catch(err => {
                errorDiv.innerText = err.message;
                errorDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newRental['total_amount'], 2) ?> Now';
            });
        }
        </script>
    <?php else: ?>
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--dark);">Self-Drive Vehicle Rental</h1>
            <p style="color: var(--text-muted);">Rent bikes, cars, SUVs, or haulers with transparent daily rates and unlimited mileage freedom.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="rental.php" method="POST" id="rentalBookingForm">
            <div class="booking-layout">
                <!-- Left Input Fields -->
                <div class="card">
                    <h3 class="card-title"><i class="fa-solid fa-calendar-days" style="color: var(--secondary);"></i> Rental Schedule & Details</h3>

                    <div class="form-group">
                        <label><i class="fa-solid fa-car" style="color: var(--secondary);"></i> Select Vehicle *</label>
                        <select name="vehicle_id" id="rental_vehicle" class="form-control" required>
                            <option value="">-- Choose Vehicle for Rental --</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" 
                                        data-daily="<?= $v['price_per_day'] ?>"
                                        <?= ($selectedVehicleId === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?> (<?= $v['category'] ?>) - <?= formatCurrency($v['price_per_day']) ?> / day
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fa-regular fa-calendar-plus"></i> Start Date *</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($presetStartDate) ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-regular fa-calendar-check"></i> End Date *</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($presetEndDate) ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Pickup / Delivery Address *</label>
                        <input type="text" name="pickup_address" class="form-control" placeholder="Enter full address or city location" value="<?= htmlspecialchars($currentUser['address'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-id-card" style="color: var(--accent);"></i> Driving License Number *</label>
                        <input type="text" name="driving_license_number" class="form-control" placeholder="e.g. DL-984214-B" required>
                        <small style="color: var(--text-muted); font-size: 11px;">Required for insurance verification and key handover.</small>
                    </div>
                </div>

                <!-- Right Rental Cost Calculator -->
                <div>
                    <div class="card" style="position: sticky; top: 100px;">
                        <h3 class="card-title"><i class="fa-solid fa-receipt" style="color: var(--accent);"></i> Rental Price Calculation</h3>

                        <div class="fare-breakdown">
                            <div class="fare-row">
                                <span>Daily Rental Rate:</span>
                                <strong id="daily_rate_display">$0.00</strong>
                            </div>
                            <div class="fare-row">
                                <span>Calculated Duration:</span>
                                <strong id="rental_days_display">0 Days</strong>
                            </div>
                            <div class="fare-row total">
                                <span>Total Rental Cost:</span>
                                <span id="rental_total_display" style="color: var(--secondary);">$0.00</span>
                            </div>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" name="total_days" id="rental_days_input" value="1">
                        <input type="hidden" name="total_amount" id="rental_total_input" value="0.00">

                        <div style="background: #f0fdf4; border-radius: var(--radius-sm); padding: 12px; margin-bottom: 20px; font-size: 12px; color: #166534;">
                            <i class="fa-solid fa-circle-check"></i> <strong>Includes:</strong> Full Comprehensive Insurance, 24/7 Roadside Assistance, and Clean Tank Handover.
                        </div>

                        <button type="submit" class="btn btn-secondary btn-lg btn-block">
                            <i class="fa-solid fa-check"></i> Confirm Rental Reservation
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
