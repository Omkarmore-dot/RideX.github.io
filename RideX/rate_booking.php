<?php
// rate_booking.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Rate & Review Your Experience";

requireLogin();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$db = getDB();

$type = isset($_GET['type']) && $_GET['type'] === 'rental' ? 'rental' : 'ride';
$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;

$error = '';

// Verify booking and vehicle
if ($type === 'ride') {
    $stmt = $db->prepare("SELECT b.*, v.name as vehicle_name, v.category, v.image_url 
                          FROM bookings b 
                          JOIN vehicles v ON b.vehicle_id = v.id 
                          WHERE b.id = ? AND b.user_id = ?");
} else {
    $stmt = $db->prepare("SELECT r.*, v.name as vehicle_name, v.category, v.image_url 
                          FROM rentals r 
                          JOIN vehicles v ON r.vehicle_id = v.id 
                          WHERE r.id = ? AND r.user_id = ?");
}
$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    setFlash('error', 'Booking record not found or access denied.');
    header("Location: dashboard.php");
    exit();
}

$vehicleId = (int)$record['vehicle_id'];

// Check if already rated
$stmtCheck = $db->prepare("SELECT id FROM ratings WHERE booking_type = ? AND booking_id = ? AND user_id = ?");
$stmtCheck->bind_param("sii", $type, $bookingId, $userId);
$stmtCheck->execute();
if ($stmtCheck->get_result()->num_rows > 0) {
    setFlash('info', 'You have already reviewed this booking. Thank you!');
    header("Location: dashboard.php");
    exit();
}

// Handle Rating Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 5);
    $review = trim($_POST['review'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a star rating between 1 and 5.";
    } elseif (empty($review)) {
        $error = "Please provide a brief review describing your experience.";
    } else {
        $stmtInsert = $db->prepare("INSERT INTO ratings (user_id, booking_type, booking_id, vehicle_id, rating, review) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtInsert->bind_param("isiiss", $userId, $type, $bookingId, $vehicleId, $rating, $review);
        
        if ($stmtInsert->execute()) {
            createNotification($userId, "Review Submitted", "Thank you for reviewing your {$record['vehicle_name']} {$type}! Your feedback helps maintain top service standards.", "vehicle_details.php?id={$vehicleId}");
            setFlash('success', 'Thank you! Your rating and review have been published.');
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Failed to save review: " . $db->error;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #fef3c7; color: #d97706; font-size: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fa-solid fa-star"></i>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">Rate Your Trip</h1>
            <p style="color: var(--text-muted); font-size: 14px;">How was your experience with <strong><?= htmlspecialchars($record['vehicle_name']) ?></strong>?</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="rate_booking.php?type=<?= $type ?>&id=<?= $bookingId ?>" method="POST">
            <!-- Star Rating Input -->
            <div class="form-group" style="text-align: center; margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 10px; font-size: 14px;">Select Star Rating</label>
                <div class="rating-input" style="justify-content: center;">
                    <input type="radio" id="star5" name="rating" value="5" checked><label for="star5" title="5 stars"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star"><i class="fa-solid fa-star"></i></label>
                </div>
            </div>

            <!-- Review Textarea -->
            <div class="form-group">
                <label>Your Review & Feedback *</label>
                <textarea name="review" class="form-control" style="min-height: 120px;" placeholder="Tell us about the vehicle cleanliness, comfort, performance, and driver professionalism..." required></textarea>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;"><i class="fa-solid fa-paper-plane"></i> Submit Review</button>
                <a href="dashboard.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
