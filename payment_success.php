<?php
// payment_success.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Payment Successful";

requireLogin();

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$currentUser = getCurrentUser();
$db = getDB();

if (!$type || !$id || !in_array($type, ['ride', 'rental', 'courier'], true)) {
    header("Location: dashboard.php");
    exit;
}

// Only show the success page for a booking that is actually marked Paid
// in the database by the verified payment flow.
if ($type === 'ride') {
    $stmt = $db->prepare("SELECT payment_status FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
} elseif ($type === 'rental') {
    $stmt = $db->prepare("SELECT payment_status FROM rentals WHERE id = ? AND user_id = ? LIMIT 1");
} else {
    $stmt = $db->prepare("SELECT payment_status FROM courier_bookings WHERE id = ? AND user_id = ? LIMIT 1");
}
$userId = (int)$currentUser['id'];
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$paymentRow = $stmt->get_result()->fetch_assoc();

if (!$paymentRow || $paymentRow['payment_status'] !== 'Paid') {
    setFlash('error', 'Payment has not been verified for this booking.');
    header("Location: dashboard.php");
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <div class="card" style="max-width: 650px; margin: 30px auto; text-align: center; padding: 40px 30px; border-top: 5px solid var(--accent);">
        <div style="width: 70px; height: 70px; border-radius: 50%; background: #dcfce7; color: #16a34a; font-size: 34px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
            <i class="fa-solid fa-check"></i>
        </div>
        <h1 style="font-size: 26px; font-weight: 800; color: var(--dark); margin-bottom: 6px;">Payment Successful!</h1>
        <p style="color: var(--text-muted); margin-bottom: 24px;">Your payment has been successfully verified and recorded.</p>
        
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <?php if ($type === 'ride'): ?>
                <a href="dashboard.php#bookings" class="btn btn-primary"><i class="fa-solid fa-table-list"></i> View in Dashboard</a>
                <a href="booking.php" class="btn btn-outline"><i class="fa-solid fa-plus"></i> Book Another Ride</a>
            <?php elseif ($type === 'rental'): ?>
                <a href="dashboard.php#rentals" class="btn btn-secondary"><i class="fa-solid fa-list-check"></i> View in Rental History</a>
                <a href="rental.php" class="btn btn-outline"><i class="fa-solid fa-plus"></i> Rent Another Vehicle</a>
            <?php elseif ($type === 'courier'): ?>
                <!-- Courier needs tracking code, let's just go to dashboard -->
                <a href="dashboard.php#courier" class="btn btn-primary"><i class="fa-solid fa-box"></i> View in Dashboard</a>
                <a href="courier.php" class="btn btn-outline"><i class="fa-solid fa-plus"></i> Send Another Parcel</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
