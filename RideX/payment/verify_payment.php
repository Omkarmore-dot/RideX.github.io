<?php
// payment/verify_payment.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$razorpayOrderId = trim($_POST['razorpay_order_id'] ?? '');
$razorpayPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
$razorpaySignature = trim($_POST['razorpay_signature'] ?? '');
$bookingType = trim($_POST['booking_type'] ?? '');
$bookingId = (int)($_POST['booking_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature || $bookingId <= 0 ||
    !in_array($bookingType, ['ride', 'rental', 'courier'], true)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid payment fields']);
    exit;
}

$db = getDB();

// Load the server-side order record. This prevents a user from using a valid
// Razorpay signature to confirm a different booking.
$stmt = $db->prepare(
    "SELECT id, amount, status
     FROM payments
     WHERE booking_type = ? AND booking_id = ? AND razorpay_order_id = ?
     ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("sis", $bookingType, $bookingId, $razorpayOrderId);
$stmt->execute();
$paymentRecord = $stmt->get_result()->fetch_assoc();

if (!$paymentRecord) {
    echo json_encode(['success' => false, 'message' => 'Razorpay order is not linked to this booking']);
    exit;
}

if ($paymentRecord['status'] === 'paid') {
    echo json_encode(['success' => true, 'message' => 'Payment was already verified']);
    exit;
}

if ($paymentRecord['status'] !== 'created') {
    echo json_encode(['success' => false, 'message' => 'Payment order is no longer valid']);
    exit;
}

$expectedAmount = 0.0;
$bookingCode = '';
$bookingUserId = 0;

if ($bookingType === 'ride') {
    $q = $db->prepare("SELECT user_id, booking_code, total_fare AS amount, payment_status FROM bookings WHERE id = ? LIMIT 1");
} elseif ($bookingType === 'rental') {
    $q = $db->prepare("SELECT user_id, rental_code AS booking_code, total_amount AS amount, payment_status FROM rentals WHERE id = ? LIMIT 1");
} else {
    $q = $db->prepare("SELECT user_id, tracking_code AS booking_code, delivery_fee AS amount, payment_status FROM courier_bookings WHERE id = ? LIMIT 1");
}
$q->bind_param("i", $bookingId);
$q->execute();
$booking = $q->get_result()->fetch_assoc();

if (!$booking || (int)$booking['user_id'] !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or access denied']);
    exit;
}

$expectedAmount = (float)$booking['amount'];
$bookingCode = (string)$booking['booking_code'];
$recordedAmount = (float)$paymentRecord['amount'];

if ($expectedAmount <= 0 || abs($expectedAmount - $recordedAmount) > 0.001) {
    echo json_encode(['success' => false, 'message' => 'Payment amount does not match the booking']);
    exit;
}

// Razorpay's documented signature verification:
// HMAC-SHA256(order_id + "|" + payment_id, key_secret)
$generatedSignature = hash_hmac(
    'sha256',
    $razorpayOrderId . '|' . $razorpayPaymentId,
    RAZORPAY_KEY_SECRET
);

if (!hash_equals($generatedSignature, $razorpaySignature)) {
    $fail = $db->prepare(
        "UPDATE payments
         SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'failed'
         WHERE id = ? AND status = 'created'"
    );
    $fail->bind_param("ssi", $razorpayPaymentId, $razorpaySignature, $paymentRecord['id']);
    $fail->execute();

    echo json_encode(['success' => false, 'message' => 'Payment signature verification failed. Booking not confirmed.']);
    exit;
}

// Signature is valid. Only now mark the payment and booking as paid/confirmed.
$db->begin_transaction();

try {
    $updPayment = $db->prepare(
        "UPDATE payments
         SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'paid'
         WHERE id = ? AND status = 'created'"
    );
    $updPayment->bind_param("ssi", $razorpayPaymentId, $razorpaySignature, $paymentRecord['id']);
    if (!$updPayment->execute() || $updPayment->affected_rows !== 1) {
        throw new Exception('Payment record could not be finalized');
    }

    if ($bookingType === 'ride') {
        $upd = $db->prepare(
            "UPDATE bookings
             SET payment_status = 'Paid', payment_method = 'Razorpay', status = 'Confirmed'
             WHERE id = ? AND user_id = ? AND payment_status <> 'Paid'"
        );
        $upd->bind_param("ii", $bookingId, $userId);
        if (!$upd->execute() || $upd->affected_rows !== 1) {
            throw new Exception('Ride booking could not be confirmed');
        }

        createNotification(
            $userId,
            "Ride Payment Confirmed ({$bookingCode})",
            "Your ride payment was verified successfully. Your ride is now confirmed.",
            "dashboard.php#bookings"
        );

    } elseif ($bookingType === 'rental') {
        $upd = $db->prepare(
            "UPDATE rentals
             SET payment_status = 'Paid', status = 'Active'
             WHERE id = ? AND user_id = ? AND payment_status <> 'Paid'"
        );
        $upd->bind_param("ii", $bookingId, $userId);
        if (!$upd->execute() || $upd->affected_rows !== 1) {
            throw new Exception('Vehicle rental could not be activated');
        }

        $vehicleStmt = $db->prepare("SELECT vehicle_id FROM rentals WHERE id = ? AND user_id = ? LIMIT 1");
        $vehicleStmt->bind_param("ii", $bookingId, $userId);
        $vehicleStmt->execute();
        $vehicle = $vehicleStmt->get_result()->fetch_assoc();

        if (!$vehicle) {
            throw new Exception('Rental vehicle could not be found');
        }

        $vehicleUpdate = $db->prepare("UPDATE vehicles SET status = 'rented' WHERE id = ?");
        $vehicleUpdate->bind_param("i", $vehicle['vehicle_id']);
        if (!$vehicleUpdate->execute()) {
            throw new Exception('Rental vehicle status could not be updated');
        }

        createNotification(
            $userId,
            "Vehicle Rental Payment Confirmed ({$bookingCode})",
            "Your rental payment was verified successfully. Your self-drive rental is now active.",
            "dashboard.php#rentals"
        );

    } else {
        $upd = $db->prepare(
            "UPDATE courier_bookings
             SET payment_status = 'Paid', current_status = 'Booking Confirmed'
             WHERE id = ? AND user_id = ? AND payment_status <> 'Paid'"
        );
        $upd->bind_param("ii", $bookingId, $userId);
        if (!$upd->execute() || $upd->affected_rows !== 1) {
            throw new Exception('Courier booking could not be confirmed');
        }

        $track = $db->prepare(
            "INSERT INTO courier_tracking
             (courier_id, status, location, remarks)
             VALUES (?, 'Booking Confirmed', 'RideX Central Dispatch Hub', 'Payment verified. Parcel booking confirmed and pickup scheduling initiated.')"
        );
        $track->bind_param("i", $bookingId);
        if (!$track->execute()) {
            throw new Exception('Courier tracking record could not be created');
        }

        createNotification(
            $userId,
            "Courier Payment Confirmed ({$bookingCode})",
            "Your courier payment was verified successfully. Your shipment is now confirmed.",
            "tracking.php?code=" . urlencode($bookingCode)
        );
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Payment verified and booking confirmed']);
} catch (Throwable $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => 'Payment was verified, but the booking could not be finalized. Please contact support.']);
}
?>
