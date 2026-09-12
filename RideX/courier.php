<?php
// courier.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Courier & Parcel Delivery Booking";

requireLogin();

$currentUser = getCurrentUser();
$db = getDB();

$error = '';
$courierConfirmed = false;
$newCourier = null;

// Handle POST Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderName = trim($_POST['sender_name'] ?? '');
    $senderPhone = trim($_POST['sender_phone'] ?? '');
    $pickupAddress = trim($_POST['pickup_address'] ?? '');
    $receiverName = trim($_POST['receiver_name'] ?? '');
    $receiverPhone = trim($_POST['receiver_phone'] ?? '');
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $parcelType = trim($_POST['parcel_type'] ?? 'Documents');
    $parcelWeight = (float)($_POST['parcel_weight'] ?? 1.0);
    $deliveryOption = trim($_POST['delivery_option'] ?? 'Standard Delivery');

    if (empty($senderName) || empty($senderPhone) || empty($pickupAddress) ||
        empty($receiverName) || empty($receiverPhone) || empty($deliveryAddress) ||
        $parcelWeight <= 0) {
        $error = "Please fill in all sender, receiver, and parcel fields.";
    } else {
        // Calculate Delivery Fee Server-Side
        $basePrice = 40.00;
        $perKgRate = 15.00;
        if ($deliveryOption === 'Express Delivery') {
            $basePrice = 70.00;
            $perKgRate = 25.00;
        } elseif ($deliveryOption === 'Same Day Delivery') {
            $basePrice = 100.00;
            $perKgRate = 35.00;
        }

        $deliveryFee = $basePrice + (max(0, $parcelWeight - 1) * $perKgRate);

        // Generate Unique Tracking Code e.g. RX-EXP-75914
        $trackingCode = "RX-EXP-" . rand(10000, 99999);
        $userId = $currentUser ? (int)$currentUser['id'] : null;

        // Insert into courier_bookings
        $stmt = $db->prepare("INSERT INTO courier_bookings (tracking_code, user_id, sender_name, sender_phone, pickup_address, receiver_name, receiver_phone, delivery_address, parcel_type, parcel_weight, delivery_option, delivery_fee, current_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Payment Pending')");

        $stmt->bind_param("sisssssssdsd", 
            $trackingCode, 
            $userId, 
            $senderName, 
            $senderPhone, 
            $pickupAddress, 
            $receiverName, 
            $receiverPhone, 
            $deliveryAddress, 
            $parcelType, 
            $parcelWeight, 
            $deliveryOption, 
            $deliveryFee
        );

        if ($stmt->execute()) {
            $courierId = $stmt->insert_id;

            // Insert initial milestone in courier_tracking
            $stmtTrack = $db->prepare("INSERT INTO courier_tracking (courier_id, status, location, remarks) VALUES (?, 'Booking Confirmed', 'RideX Central Dispatch Hub', 'Parcel booking registered. Pickup agent scheduling initiated.')");
            $stmtTrack->bind_param("i", $courierId);
            $stmtTrack->execute();

            if ($userId) {
                createNotification(
                    $userId, 
                    "Courier Registered ({$trackingCode})", 
                    "Your shipment to {$receiverName} has been booked. Track live progress anytime.", 
                    "tracking.php?code={$trackingCode}"
                );
            }

            $courierConfirmed = true;
            $newCourier = [
                'tracking_code' => $trackingCode,
                'receiver' => $receiverName,
                'delivery_address' => $deliveryAddress,
                'option' => $deliveryOption,
                'weight' => $parcelWeight,
                'fee' => $deliveryFee
            ];
        } else {
            $error = "Failed to create courier booking: " . $db->error;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <?php if ($courierConfirmed && $newCourier): ?>
        <!-- Courier Confirmation Card -->
        <div class="card" style="max-width: 650px; margin: 30px auto; text-align: center; padding: 40px 30px; border-top: 5px solid var(--accent);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #dcfce7; color: #16a34a; font-size: 34px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fa-solid fa-box-check"></i>
            </div>
            <h1 style="font-size: 26px; font-weight: 800; color: var(--dark); margin-bottom: 6px;">Courier Booking Saved!</h1>
            <p style="color: var(--text-muted); margin-bottom: 24px;">Please complete the payment to dispatch your parcel.</p>

            <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 20px; text-align: left; margin-bottom: 25px; border: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px;">
                    <span style="color: var(--text-muted); font-size: 14px;">Tracking ID:</span>
                    <strong style="color: var(--primary); font-size: 18px; font-family: monospace; letter-spacing: 0.5px;"><?= $newCourier['tracking_code'] ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Recipient:</span>
                    <strong><?= htmlspecialchars($newCourier['receiver']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Delivery Address:</span>
                    <span><?= htmlspecialchars($newCourier['delivery_address']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Speed Option:</span>
                    <span class="status-pill status-confirmed"><?= htmlspecialchars($newCourier['option']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: var(--text-muted);">Parcel Weight:</span>
                    <span><?= number_format($newCourier['weight'], 2) ?> kg</span>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px dashed var(--border); padding-top: 12px; margin-top: 10px; font-size: 18px; font-weight: 800; color: var(--dark);">
                    <span>Total Delivery Fee:</span>
                    <span style="color: var(--accent);"><?= formatCurrency($newCourier['fee']) ?></span>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <button id="rzp-button1" class="btn btn-primary" style="background-color: #2b8256; border-color: #2b8256;"><i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newCourier['fee'], 2) ?> Now</button>
                <a href="dashboard.php#courier" class="btn btn-outline">Pay Later</a>
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
                body: new URLSearchParams({booking_type: 'courier', booking_id: '<?= $courierId ?>'})
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
                    "description": "Payment for Courier " + '<?= $newCourier['tracking_code'] ?>',
                    "order_id": data.razorpay_order_id,
                    "handler": function (response) {
                        // Verify Payment
                        fetch('payment/verify_payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({razorpay_payment_id: response.razorpay_payment_id, razorpay_order_id: response.razorpay_order_id, razorpay_signature: response.razorpay_signature, booking_type: 'courier', booking_id: '<?= $courierId ?>'})
                        })
                        .then(res => res.json())
                        .then(verifyData => {
                            if (verifyData.success) {
                                window.location.href = 'payment_success.php?type=courier&id=<?= $courierId ?>';
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
                    btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newCourier['fee'], 2) ?> Now';
                });
                rzp1.open();
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newCourier['fee'], 2) ?> Now';
            })
            .catch(err => {
                errorDiv.innerText = err.message;
                errorDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($newCourier['fee'], 2) ?> Now';
            });
        }
        </script>
    <?php else: ?>
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--dark);">Send a Courier Parcel</h1>
            <p style="color: var(--text-muted);">Reliable point-to-point courier delivery with end-to-end live tracking.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="courier.php" method="POST">
            <div class="booking-layout">
                <!-- Left Input Cards -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <!-- Sender Details -->
                    <div class="card">
                        <h3 class="card-title"><i class="fa-solid fa-user-arrow-up-long" style="color: var(--primary);"></i> 1. Sender Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Sender Full Name *</label>
                                <input type="text" name="sender_name" class="form-control" value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>" placeholder="e.g. Alex Morgan" required>
                            </div>
                            <div class="form-group">
                                <label>Sender Phone *</label>
                                <input type="tel" name="sender_phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="e.g. +1 555-0199" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Pickup Address *</label>
                            <textarea name="pickup_address" class="form-control" placeholder="Complete address including apartment, street, and landmark" required><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Receiver Details -->
                    <div class="card">
                        <h3 class="card-title"><i class="fa-solid fa-user-arrow-down-long" style="color: var(--secondary);"></i> 2. Receiver Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Receiver Full Name *</label>
                                <input type="text" name="receiver_name" class="form-control" placeholder="e.g. David Wilson" required>
                            </div>
                            <div class="form-group">
                                <label>Receiver Phone *</label>
                                <input type="tel" name="receiver_phone" class="form-control" placeholder="e.g. +1 555-0782" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Delivery Address *</label>
                            <textarea name="delivery_address" class="form-control" placeholder="Destination address, building/door number, and area" required></textarea>
                        </div>
                    </div>

                    <!-- Parcel Specifications -->
                    <div class="card">
                        <h3 class="card-title"><i class="fa-solid fa-box-open" style="color: var(--accent);"></i> 3. Parcel Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Parcel Classification</label>
                                <select name="parcel_type" class="form-control">
                                    <option value="Documents & Papers">Documents & Papers</option>
                                    <option value="Electronics & Gadgets">Electronics & Gadgets</option>
                                    <option value="Fragile Items">Fragile Items</option>
                                    <option value="Apparel & Clothing">Apparel & Clothing</option>
                                    <option value="Food & Groceries">Food & Groceries</option>
                                    <option value="Heavy Cargo">Heavy Cargo</option>
                                    <option value="Other">Other Goods</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Parcel Weight (in KG) *</label>
                                <input type="number" name="parcel_weight" id="parcel_weight" class="form-control" value="1.0" min="0.1" step="0.1" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="margin-bottom: 10px; display: block;">Delivery Speed Option</label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                                <label style="display: flex; align-items: center; gap: 10px; border: 1px solid var(--border); padding: 12px; border-radius: var(--radius-md); cursor: pointer; background: #fff;">
                                    <input type="radio" name="delivery_option" value="Standard Delivery" checked>
                                    <div>
                                        <strong style="display: block; font-size: 14px;">Standard Delivery</strong>
                                        <small style="color: var(--text-muted);">2-3 Business Days</small>
                                    </div>
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; border: 1px solid var(--border); padding: 12px; border-radius: var(--radius-md); cursor: pointer; background: #fff;">
                                    <input type="radio" name="delivery_option" value="Express Delivery">
                                    <div>
                                        <strong style="display: block; font-size: 14px;">Express Delivery</strong>
                                        <small style="color: var(--text-muted);">Next Day Guaranteed</small>
                                    </div>
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; border: 1px solid var(--border); padding: 12px; border-radius: var(--radius-md); cursor: pointer; background: #fff;">
                                    <input type="radio" name="delivery_option" value="Same Day Delivery">
                                    <div>
                                        <strong style="display: block; font-size: 14px;">Same Day Delivery</strong>
                                        <small style="color: var(--text-muted);">Within 6-8 Hours</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Estimated Cost Summary -->
                <div>
                    <div class="card" style="position: sticky; top: 100px;">
                        <h3 class="card-title"><i class="fa-solid fa-receipt" style="color: var(--primary);"></i> Delivery Cost Summary</h3>

                        <div class="fare-breakdown">
                            <div class="fare-row">
                                <span>Handling & Dispatch:</span>
                                <strong>Included</strong>
                            </div>
                            <div class="fare-row">
                                <span>Real-time GPS Tracking:</span>
                                <strong>Free</strong>
                            </div>
                            <div class="fare-row total">
                                <span>Estimated Courier Fee:</span>
                                <span id="courier_fee_display" style="color: var(--accent);">$40.00</span>
                            </div>
                        </div>

                        <input type="hidden" name="delivery_fee" id="courier_fee_input" value="40.00">

                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="fa-solid fa-paper-plane"></i> Confirm Courier Dispatch
                        </button>

                        <div style="margin-top: 20px; font-size: 12px; color: var(--text-muted); line-height: 1.5;">
                            <i class="fa-solid fa-lock" style="color: var(--accent);"></i> Secure packaging & tamper-evident verification code provided to the receiver upon arrival.
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
