<?php
// payment/create_order.php
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

$bookingType = trim($_POST['booking_type'] ?? '');
$bookingId = (int)($_POST['booking_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if (!in_array($bookingType, ['ride', 'rental', 'courier'], true) || $bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking details']);
    exit;
}

$db = getDB();
$amount = 0.0;
$receipt = '';
$paymentStatus = 'Pending';
$bookingExists = false;

switch ($bookingType) {
    case 'ride':
        $stmt = $db->prepare("SELECT booking_code, total_fare AS amount, payment_status FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $bookingId, $userId);
        break;
    case 'rental':
        $stmt = $db->prepare("SELECT rental_code AS booking_code, total_amount AS amount, payment_status FROM rentals WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $bookingId, $userId);
        break;
    case 'courier':
        $stmt = $db->prepare("SELECT tracking_code AS booking_code, delivery_fee AS amount, payment_status FROM courier_bookings WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $bookingId, $userId);
        break;
}

if (!$stmt || !$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Unable to load booking']);
    exit;
}

$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or access denied']);
    exit;
}

$amount = (float)$row['amount'];
$receipt = (string)$row['booking_code'];
$paymentStatus = (string)$row['payment_status'];
$bookingExists = true;

if (!$bookingExists || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking amount']);
    exit;
}

if ($paymentStatus === 'Paid') {
    echo json_encode(['success' => false, 'message' => 'This booking has already been paid']);
    exit;
}

// Razorpay requires amount in paise. The amount comes from the database,
// never from a browser-supplied amount.
$amountInPaise = (int)round($amount * 100);

$payload = json_encode([
    'amount' => $amountInPaise,
    'currency' => 'INR',
    'receipt' => substr($receipt, 0, 40),
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError) {
    echo json_encode(['success' => false, 'message' => 'Unable to connect to Razorpay: ' . $curlError]);
    exit;
}

$orderData = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || empty($orderData['id'])) {
    $errMsg = $orderData['error']['description'] ?? 'Failed to create Razorpay order';
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

if ((int)($orderData['amount'] ?? 0) !== $amountInPaise || ($orderData['currency'] ?? '') !== 'INR') {
    echo json_encode(['success' => false, 'message' => 'Razorpay returned an unexpected order amount']);
    exit;
}

// Keep a server-side record tying the Razorpay order to this exact booking.
$paymentStmt = $db->prepare(
    "INSERT INTO payments (booking_type, booking_id, razorpay_order_id, amount, currency, status)
     VALUES (?, ?, ?, ?, 'INR', 'created')"
);
$paymentStmt->bind_param("sisd", $bookingType, $bookingId, $orderData['id'], $amount);
if (!$paymentStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Payment order could not be recorded']);
    exit;
}

$currentUser = getCurrentUser();

echo json_encode([
    'success' => true,
    'razorpay_order_id' => $orderData['id'],
    'amount' => (int)$orderData['amount'],
    'currency' => 'INR',
    'key_id' => RAZORPAY_KEY_ID,
    'user_name' => $currentUser['full_name'] ?? '',
    'user_email' => $currentUser['email'] ?? '',
    'user_phone' => $currentUser['phone'] ?? ''
]);
?>
