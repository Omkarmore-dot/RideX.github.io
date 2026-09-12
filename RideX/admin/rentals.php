<?php
// admin/rentals.php
require_once __DIR__ . '/../config/db.php';
enforceAdminAreaAccess();
$adminTitle = "Vehicle Rental Administration";

$db = getDB();

// Handle Rental Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_rental_status') {
    $rentalId = (int)($_POST['rental_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $requestedPaymentStatus = trim($_POST['payment_status'] ?? '');

    $validStatuses = ['Pending Approval', 'Active', 'Completed', 'Cancelled'];

    if ($rentalId && in_array($newStatus, $validStatuses, true)) {
        $check = $db->prepare("SELECT payment_status FROM rentals WHERE id = ? LIMIT 1");
        $check->bind_param("i", $rentalId);
        $check->execute();
        $current = $check->get_result()->fetch_assoc();

        if (!$current) {
            setFlash('error', "Rental not found.");
        } elseif ($requestedPaymentStatus === 'Paid' && $current['payment_status'] !== 'Paid') {
            setFlash('error', "Payment can only be marked Paid after successful Razorpay verification.");
        } elseif ($current['payment_status'] !== 'Paid' && !in_array($newStatus, ['Cancelled', 'Pending Approval'], true)) {
            setFlash('error', "Unpaid rentals cannot be activated.");
        } else {
            $paymentStatus = $current['payment_status'];
            $stmt = $db->prepare("UPDATE rentals SET status = ?, payment_status = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newStatus, $paymentStatus, $rentalId);

            if ($stmt->execute()) {
                if (in_array($newStatus, ['Completed', 'Cancelled'], true)) {
                    $stmtVeh = $db->prepare("SELECT vehicle_id, user_id, rental_code FROM rentals WHERE id = ?");
                    $stmtVeh->bind_param("i", $rentalId);
                    $stmtVeh->execute();
                    $rInfo = $stmtVeh->get_result()->fetch_assoc();

                    if ($rInfo) {
                        $db->query("UPDATE vehicles SET status = 'available' WHERE id = " . (int)$rInfo['vehicle_id']);
                        createNotification(
                            $rInfo['user_id'],
                            "Rental Status: {$newStatus}",
                            "Your rental booking {$rInfo['rental_code']} is now marked as {$newStatus}.",
                            "dashboard.php#rentals"
                        );
                    }
                }
                setFlash('success', "Rental #{$rentalId} status updated to '{$newStatus}'.");
            } else {
                setFlash('error', "Failed to update rental: " . $db->error);
            }
        }
    }

    header("Location: rentals.php");
    exit();
}

$statusFilter = trim($_GET['status'] ?? '');
$sql = "SELECT r.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               v.name as vehicle_name, v.category, v.plate_number 
        FROM rentals r 
        JOIN users u ON r.user_id = u.id 
        JOIN vehicles v ON r.vehicle_id = v.id 
        WHERE 1=1";

if (!empty($statusFilter)) {
    $sql .= " AND r.status = '" . $db->real_escape_string($statusFilter) . "'";
}
$sql .= " ORDER BY r.created_at DESC";

$rentals = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--admin-text); margin: 0;">Vehicle Rentals Management</h1>
        <p style="color: var(--admin-muted); font-size: 14px; margin: 4px 0 0;">Review driver licenses, approve reservations, and process returns.</p>
    </div>

    <!-- Status Filters -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="rentals.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline' ?>">All</a>
        <a href="rentals.php?status=Pending+Approval" class="btn btn-sm <?= $statusFilter === 'Pending Approval' ? 'btn-primary' : 'btn-outline' ?>">Pending</a>
        <a href="rentals.php?status=Active" class="btn btn-sm <?= $statusFilter === 'Active' ? 'btn-primary' : 'btn-outline' ?>">Active</a>
        <a href="rentals.php?status=Completed" class="btn btn-sm <?= $statusFilter === 'Completed' ? 'btn-primary' : 'btn-outline' ?>">Completed</a>
        <a href="rentals.php?status=Cancelled" class="btn btn-sm <?= $statusFilter === 'Cancelled' ? 'btn-primary' : 'btn-outline' ?>">Cancelled</a>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-key" style="color: var(--admin-primary);"></i> All Rental Agreements (<?= count($rentals) ?>)</h3>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Rental Code</th>
                    <th>Customer</th>
                    <th>Vehicle</th>
                    <th>Rental Period</th>
                    <th>License Number</th>
                    <th>Delivery Address</th>
                    <th>Total Price</th>
                    <th>Status & Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rentals)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--admin-muted); padding: 30px;">No vehicle rentals found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rentals as $r): ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; color: var(--secondary); font-size: 15px;"><?= htmlspecialchars($r['rental_code']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($r['customer_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);">
                                    <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($r['customer_phone']) ?><br>
                                    <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($r['customer_email']) ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($r['vehicle_name']) ?></strong><br>
                                <span class="status-pill status-confirmed" style="font-size: 11px;"><?= htmlspecialchars($r['category']) ?></span><br>
                                <small style="font-family: monospace;"><?= htmlspecialchars($r['plate_number']) ?></small>
                            </td>
                            <td>
                                <strong><?= date('M d', strtotime($r['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($r['end_date'])) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= $r['total_days'] ?> Days @ <?= formatCurrency($r['price_per_day']) ?>/day</small>
                            </td>
                            <td>
                                <span style="font-family: monospace; background: #f8fafc; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--admin-border); font-weight: 700;">
                                    <?= htmlspecialchars($r['driving_license_number']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($r['pickup_address']) ?></small>
                            </td>
                            <td>
                                <strong style="color: var(--admin-primary); font-size: 15px;"><?= formatCurrency($r['total_amount']) ?></strong><br>
                                <span class="status-pill <?= $r['payment_status'] === 'Paid' ? 'status-completed' : 'status-pending' ?>" style="font-size: 10px;">
                                    <?= htmlspecialchars($r['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <form action="rentals.php" method="POST" style="display: flex; flex-direction: column; gap: 6px;">
                                    <input type="hidden" name="action" value="update_rental_status">
                                    <input type="hidden" name="rental_id" value="<?= $r['id'] ?>">

                                    <select name="status" class="form-select-sm">
                                        <option value="Pending Approval" <?= $r['status'] === 'Pending Approval' ? 'selected' : '' ?>>Pending Approval</option>
                                        <option value="Active" <?= $r['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                        <option value="Completed" <?= $r['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Cancelled" <?= $r['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>

                                    <select name="payment_status" class="form-select-sm">
                                        <option value="Paid" <?= $r['payment_status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="Pending" <?= $r['payment_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    </select>

                                    <button type="submit" class="btn btn-sm btn-secondary"><i class="fa-solid fa-save"></i> Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
