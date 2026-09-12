<?php
// admin/reviews.php
require_once __DIR__ . '/../config/db.php';
enforceAdminAreaAccess();
$adminTitle = "Ratings & Reviews Moderation";

$db = getDB();

// Handle Delete Review
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $reviewId = (int)($_GET['id'] ?? 0);
    if ($reviewId) {
        $stmt = $db->prepare("DELETE FROM ratings WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        if ($stmt->execute()) {
            setFlash('success', "Review #{$reviewId} removed.");
        }
    }
    header("Location: reviews.php");
    exit();
}

$sql = "SELECT r.*, u.full_name, u.email, v.name as vehicle_name, v.category, v.image_url 
        FROM ratings r 
        JOIN users u ON r.user_id = u.id 
        JOIN vehicles v ON r.vehicle_id = v.id 
        ORDER BY r.created_at DESC";

$reviews = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--admin-text); margin: 0;">Ratings & Reviews Moderation</h1>
        <p style="color: var(--admin-muted); font-size: 14px; margin: 4px 0 0;">Inspect verified customer feedback, star ratings, and vehicle performance.</p>
    </div>
    <div>
        <span class="user-badge" style="background: #ffffff; border: 1px solid var(--admin-border);">
            Total Reviews: <strong><?= count($reviews) ?></strong>
        </span>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-star" style="color: #f59e0b;"></i> Customer Reviews Log</h3>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Rating</th>
                    <th>Review Content</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--admin-muted); padding: 30px;">No reviews recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <tr>
                            <td>#<?= $rev['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($rev['full_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= htmlspecialchars($rev['email']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($rev['vehicle_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= htmlspecialchars($rev['category']) ?></small>
                            </td>
                            <td>
                                <span class="status-pill <?= $rev['booking_type'] === 'ride' ? 'status-confirmed' : 'status-driver-assigned' ?>">
                                    <?= ucfirst($rev['booking_type']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="star-rating">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fa-solid fa-star" style="color: <?= $s <= $rev['rating'] ? '#f59e0b' : '#cbd5e1' ?>; font-size: 13px;"></i>
                                    <?php endfor; ?>
                                </div>
                                <strong style="font-size: 12px; margin-left: 4px;"><?= $rev['rating'] ?>/5</strong>
                            </td>
                            <td>
                                <p style="font-size: 13px; margin: 0; max-width: 320px;"><?= htmlspecialchars($rev['review']) ?></p>
                            </td>
                            <td><?= date('M d, Y', strtotime($rev['created_at'])) ?></td>
                            <td>
                                <a href="reviews.php?action=delete&id=<?= $rev['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this review?');" title="Delete Review">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
