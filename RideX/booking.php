<?php
// booking.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Book an Instant Ride";

// Enforce login for booking a ride
requireLogin();

$currentUser = getCurrentUser();
$db = getDB();

// Pre-fill parameters from GET if available
$selectedVehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$presetPickup = isset($_GET['pickup']) ? trim($_GET['pickup']) : '';
$presetDrop = isset($_GET['drop']) ? trim($_GET['drop']) : '';
$presetDistance = isset($_GET['distance']) ? (float)$_GET['distance'] : 10.0;

$bookingConfirmed = false;
$newBooking = null;
$error = '';

// Handle POST Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $pickupLocation = trim($_POST['pickup_location'] ?? '');
    $dropLocation = trim($_POST['drop_location'] ?? '');
    $rideDate = trim($_POST['ride_date'] ?? '');
    $rideTime = trim($_POST['ride_time'] ?? '');
    $distanceKm = (float)($_POST['distance_km'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
    $notes = trim($_POST['notes'] ?? '');

    // Form Validation
    if (!$vehicleId || empty($pickupLocation) || empty($dropLocation) || empty($rideDate) || empty($rideTime) || $distanceKm <= 0) {
        $error = "Please fill in all required fields with a valid distance.";
    } else {
        // Fetch vehicle details from DB
        $stmtV = $db->prepare("SELECT * FROM vehicles WHERE id = ? AND status = 'available'");
        $stmtV->bind_param("i", $vehicleId);
        $stmtV->execute();
        $vehicle = $stmtV->get_result()->fetch_assoc();

        if (!$vehicle) {
            $error = "The selected vehicle is currently not available. Please choose another vehicle.";
        } else {
            // Server-Side Fare Calculation Formula: Fare = Base Fare + (Distance * Rate per KM)
            $baseFare = (float)$vehicle['base_fare'];
            $ratePerKm = (float)$vehicle['price_per_km'];
            $totalFare = $baseFare + ($distanceKm * $ratePerKm);

            // Generate Unique Booking Code e.g. RX-RIDE-62849
            $bookingCode = "RX-RIDE-" . rand(10000, 99999);

            // Insert into bookings table
            $stmtInsert = $db->prepare("INSERT INTO bookings (booking_code, user_id, vehicle_id, pickup_location, drop_location, ride_date, ride_time, distance_km, base_fare, rate_per_km, total_fare, payment_method, payment_status, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Payment Pending', ?)");
            
            $userId = (int)$currentUser['id'];
            $stmtInsert->bind_param("siissssddddss", 
                $bookingCode, 
                $userId, 
                $vehicleId, 
                $pickupLocation, 
                $dropLocation, 
                $rideDate, 
                $rideTime, 
                $distanceKm, 
                $baseFare, 
                $ratePerKm, 
                $totalFare, 
                $paymentMethod, 
                $notes
            );

            if ($stmtInsert->execute()) {
                $bookingId = $stmtInsert->insert_id;
                
                // Create user notification
                createNotification(
                    $userId, 
                    "Ride Booking Confirmed ({$bookingCode})", 
                    "Your ride for {$rideDate} at {$rideTime} has been successfully scheduled with {$vehicle['name']}.", 
                    "dashboard.php#bookings"
                );

                $bookingConfirmed = true;
                $newBooking = [
                    'code' => $bookingCode,
                    'vehicle' => $vehicle['name'],
                    'category' => $vehicle['category'],
                    'pickup' => $pickupLocation,
                    'drop' => $dropLocation,
                    'date' => $rideDate,
                    'time' => $rideTime,
                    'distance' => $distanceKm,
                    'total_fare' => $totalFare,
                    'payment_method' => $paymentMethod
                ];
            } else {
                $error = "Booking failed due to a database error: " . $db->error;
            }
        }
    }
}

// Fetch all available vehicles for the selector
$vehicles = $db->query("SELECT id, name, category, brand, plate_number, base_fare, price_per_km, image_url FROM vehicles WHERE status = 'available' ORDER BY category ASC, name ASC")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <?php if ($bookingConfirmed && $newBooking): ?>
        <!-- Booking Confirmation Screen -->
        <div class="card" style="max-width: 650px; margin: 30px auto; text-align: center; padding: 40px 30px; border-top: 5px solid var(--accent);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #dcfce7; color: #16a34a; font-size: 34px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fa-solid fa-check"></i>
            </div>
            <h1 style="font-size: 26px; font-weight: 800; color: var(--dark); margin-bottom: 6px;">Ride Booking Saved!</h1>
            <p style="color: var(--text-muted); margin-bottom: 24px;">Please complete the payment to confirm your ride.</p>

            <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 20px; text-align: left; margin-bottom: 25px; border: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px;">
                    <span style="color: var(--text-muted); font-size: 14px;">Booking Code:</span>
                    <strong id="rzp-booking-code" style="color: var(--primary); font-size: 16px; font-family: monospace;"><?= $newBooking['code'] ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Assigned Vehicle:</span>
                    <strong><?= htmlspecialchars($newBooking['vehicle']) ?> (<?= htmlspecialchars($newBooking['category']) ?>)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Pickup Location:</span>
                    <span><?= htmlspecialchars($newBooking['pickup']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Dropoff Location:</span>
                    <span><?= htmlspecialchars($newBooking['drop']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Date & Time:</span>
                    <span><?= date('M d, Y', strtotime($newBooking['date'])) ?> at <?= date('h:i A', strtotime($newBooking['time'])) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Estimated Distance:</span>
                    <span><?= number_format($newBooking['distance'], 1) ?> km</span>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px dashed var(--border); padding-top: 12px; margin-top: 10px; font-size: 18px; font-weight: 800; color: var(--dark);">
                    <span>Total Fare:</span>
                    <span style="color: var(--accent);"><?= formatCurrency($newBooking['total_fare']) ?></span>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <button id="rzp-button1" class="btn btn-primary" style="background-color: #2b8256; border-color: #2b8256;"><i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newBooking['total_fare'], 2) ?> Now</button>
                <a href="dashboard.php#bookings" class="btn btn-outline">Pay Cash to Driver</a>
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
                body: new URLSearchParams({booking_type: 'ride', booking_id: '<?= $bookingId ?>'})
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
                    "description": "Payment for Ride " + '<?= $newBooking['code'] ?>',
                    "order_id": data.razorpay_order_id,
                    "handler": function (response) {
                        // Verify Payment
                        fetch('payment/verify_payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({razorpay_payment_id: response.razorpay_payment_id, razorpay_order_id: response.razorpay_order_id, razorpay_signature: response.razorpay_signature, booking_type: 'ride', booking_id: '<?= $bookingId ?>'})
                        })
                        .then(res => res.json())
                        .then(verifyData => {
                            if (verifyData.success) {
                                window.location.href = 'payment_success.php?type=ride&id=<?= $bookingId ?>';
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
                    btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newBooking['total_fare'], 2) ?> Now';
                });
                rzp1.open();
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newBooking['total_fare'], 2) ?> Now';
            })
            .catch(err => {
                errorDiv.innerText = err.message;
                errorDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newBooking['total_fare'], 2) ?> Now';
            });
        }
        </script>
    <?php else: ?>
        <!-- Booking Form Layout -->
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--dark);">Book an Instant Ride</h1>
            <p style="color: var(--text-muted);">Calculate real-time fare based on distance and vehicle category.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="booking.php" method="POST" id="rideBookingForm">
            <div class="booking-layout">
                <!-- Left Form Column -->
                <div class="card">
                    <h3 class="card-title"><i class="fa-solid fa-route" style="color: var(--primary);"></i> Trip Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Pickup Location *</label>
                            <input type="text" name="pickup_location" class="form-control" placeholder="e.g. 100 Innovation Way" value="<?= htmlspecialchars($presetPickup) ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-location-crosshairs" style="color: var(--accent);"></i> Dropoff Location *</label>
                            <input type="text" name="drop_location" class="form-control" placeholder="e.g. Skyline Airport Gate 3" value="<?= htmlspecialchars($presetDrop) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fa-regular fa-calendar"></i> Ride Date *</label>
                            <input type="date" name="ride_date" class="form-control" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-regular fa-clock"></i> Ride Time *</label>
                            <input type="time" name="ride_time" class="form-control" value="<?= date('H:i', strtotime('+30 minutes')) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-car-side" style="color: var(--primary);"></i> Select Vehicle Category & Model *</label>
                        <select name="vehicle_id" id="ride_vehicle" class="form-control" required>
                            <option value="">-- Choose an Available Vehicle --</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" 
                                        data-base="<?= $v['base_fare'] ?>" 
                                        data-rate="<?= $v['price_per_km'] ?>"
                                        <?= ($selectedVehicleId === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($v['category']) ?> (Base: $<?= number_format($v['base_fare'], 2) ?> + $<?= number_format($v['price_per_km'], 2) ?>/km)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fa-solid fa-road"></i> Estimated Distance (KM) *</label>
                            <input type="number" name="distance_km" id="distance_km" class="form-control" value="<?= htmlspecialchars($presetDistance) ?>" min="0.5" step="0.5" required>
                            <small style="color: var(--text-muted); font-size: 11px;">Change distance to recalculate fare in real-time.</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-credit-card"></i> Payment Method</label>
                            <select name="payment_method" class="form-control">
                                <option value="Cash">Cash to Driver</option>
                                <option value="Card">Credit / Debit Card</option>
                                <option value="UPI">UPI / Instant Transfer</option>
                                <option value="Wallet">RideX Wallet</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Special Instructions / Driver Notes (Optional)</label>
                        <textarea name="notes" class="form-control" placeholder="e.g. 2 large luggage bags, please call on arrival"></textarea>
                    </div>
                </div>

                <!-- Right Live Fare Summary Column -->
                <div>
                    <div class="card" style="position: sticky; top: 100px;">
                        <h3 class="card-title"><i class="fa-solid fa-calculator" style="color: var(--accent);"></i> Real-Time Fare Breakdown</h3>

                        <div class="fare-breakdown">
                            <div class="fare-row">
                                <span>Base Fare:</span>
                                <strong id="base_fare_display">$0.00</strong>
                            </div>
                            <div class="fare-row">
                                <span>Rate per KM:</span>
                                <strong id="rate_km_display">$0.00</strong>
                            </div>
                            <div class="fare-row">
                                <span>Estimated Distance:</span>
                                <strong id="distance_display">0.0 km</strong>
                            </div>
                            <div class="fare-row total">
                                <span>Estimated Total:</span>
                                <span id="total_fare_display" style="color: var(--accent);">$0.00</span>
                            </div>
                        </div>

                        <!-- Hidden Total Fare Field for Form Submission -->
                        <input type="hidden" name="total_fare" id="total_fare_input" value="0.00">

                        <div style="background: #eff6ff; border-radius: var(--radius-sm); padding: 12px; margin-bottom: 20px; font-size: 12px; color: #1e40af;">
                            <i class="fa-solid fa-circle-info"></i> <strong>Fare Formula:</strong><br>
                            <code>Total = Base Fare + (Distance × Rate/KM)</code>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="fa-solid fa-check-circle"></i> Confirm & Book Ride
                        </button>

                        <p style="font-size: 12px; color: var(--text-muted); text-align: center; margin-top: 15px;">
                            Free cancellation up to 10 minutes before pickup.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
