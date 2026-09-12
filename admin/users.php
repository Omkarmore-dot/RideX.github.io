<?php
// admin/users.php
require_once __DIR__ . '/../config/db.php';
enforceAdminAreaAccess();
$adminTitle = "Registered Users Management";

$db = getDB();

// Handle Status Toggle (active / suspended)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $userId = (int)($_GET['id'] ?? 0);
    $newStatus = trim($_GET['status'] ?? 'active');
    
    if ($userId && in_array($newStatus, ['active', 'suspended'])) {
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $userId);
        if ($stmt->execute()) {
            setFlash('success', "User #{$userId} status changed to '{$newStatus}'.");
        }
    }
    header("Location: users.php");
    exit();
}

// Fetch all users with counts of their bookings, rentals, and couriers
$sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) as ride_count,
               (SELECT COUNT(*) FROM rentals r WHERE r.user_id = u.id) as rental_count,
               (SELECT COUNT(*) FROM courier_bookings c WHERE c.user_id = u.id) as courier_count
        FROM users u 
        ORDER BY u.created_at DESC";

$users = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--admin-text); margin: 0;">Registered Customer Accounts</h1>
        <p style="color: var(--admin-muted); font-size: 14px; margin: 4px 0 0;">View customer profiles, track total usage, and toggle account access.</p>
    </div>
    <div>
        <span class="user-badge" style="background: #ffffff; border: 1px solid var(--admin-border);">
            Total Customers: <strong><?= count($users) ?></strong>
        </span>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-users" style="color: var(--admin-primary);"></i> User Registry</h3>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Customer Name</th>
                    <th>Email & Contact</th>
                    <th>Registered Address</th>
                    <th>Activity Stats</th>
                    <th>Joined On</th>
                    <th>Account Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--admin-muted); padding: 30px;">No registered users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong style="font-family: monospace;">#<?= $u['id'] ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <span><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($u['email']) ?></span><br>
                                <small style="color: var(--admin-muted);"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($u['phone']) ?></small>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($u['address'] ?? 'Not specified') ?></small>
                            </td>
                            <td>
                                <small>
                                    <i class="fa-solid fa-taxi"></i> <?= $u['ride_count'] ?> rides<br>
                                    <i class="fa-solid fa-key"></i> <?= $u['rental_count'] ?> rentals<br>
                                    <i class="fa-solid fa-box"></i> <?= $u['courier_count'] ?> parcels
                                </small>
                            </td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <span class="status-pill status-<?= $u['status'] === 'active' ? 'completed' : 'cancelled' ?>">
                                    <?= htmlspecialchars($u['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <a href="users.php?action=toggle_status&id=<?= $u['id'] ?>&status=suspended" class="btn btn-sm btn-danger" onclick="return confirm('Suspend this user account?');" title="Suspend Account">
                                        <i class="fa-solid fa-ban"></i> Suspend
                                    </a>
                                <?php else: ?>
                                    <a href="users.php?action=toggle_status&id=<?= $u['id'] ?>&status=active" class="btn btn-sm btn-primary" title="Reactivate Account">
                                        <i class="fa-solid fa-check"></i> Activate
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
