<?php
// admin/courier.php
require_once __DIR__ . '/../config/db.php';
enforceAdminAreaAccess();
$adminTitle = "Courier & Logistics Management";

$db = getDB();

// Handle Status & Milestone Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tracking_milestone') {
    $courierId = (int)($_POST['courier_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $location = trim($_POST['location'] ?? 'RideX Logistics Center');
    $remarks = trim($_POST['remarks'] ?? '');

    $validStatuses = ['Booking Confirmed', 'Pickup Assigned', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered', 'Cancelled'];
    
    if ($courierId && in_array($newStatus, $validStatuses, true)) {
        $check = $db->prepare("SELECT payment_status FROM courier_bookings WHERE id = ? LIMIT 1");
        $check->bind_param("i", $courierId);
        $check->execute();
        $current = $check->get_result()->fetch_assoc();

        if (!$current) {
            setFlash('error', "Courier booking not found.");
        } elseif ($current['payment_status'] !== 'Paid' && $newStatus !== 'Cancelled') {
            setFlash('error', "Unpaid courier bookings cannot be dispatched.");
        } else {
            if (empty($location)) {
                $location = "Central Logistics Hub";
            }

            addCourierTrackingEvent($courierId, $newStatus, $location, $remarks);

            $stmtC = $db->prepare("SELECT user_id, tracking_code, receiver_name FROM courier_bookings WHERE id = ?");
            $stmtC->bind_param("i", $courierId);
            $stmtC->execute();
            $cRow = $stmtC->get_result()->fetch_assoc();

            if ($cRow && $cRow['user_id']) {
                createNotification(
                    $cRow['user_id'], 
                    "Shipment Update: {$newStatus}", 
                    "Your package ({$cRow['tracking_code']}) destined for {$cRow['receiver_name']} is now: {$newStatus} ({$location}).", 
                    "tracking.php?code={$cRow['tracking_code']}"
                );
            }

            setFlash('success', "Courier #{$courierId} tracking updated to '{$newStatus}' at {$location}.");
        }
    } else {
        setFlash('error', "Invalid courier milestone status.");
    }
    header("Location: courier.php");
    exit();
}

$statusFilter = trim($_GET['status'] ?? '');
$sql = "SELECT * FROM courier_bookings WHERE 1=1";
if (!empty($statusFilter)) {
    $sql .= " AND current_status = '" . $db->real_escape_string($statusFilter) . "'";
}
$sql .= " ORDER BY created_at DESC";

$couriers = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--admin-text); margin: 0;">Courier Logistics & Tracking Console</h1>
        <p style="color: var(--admin-muted); font-size: 14px; margin: 4px 0 0;">Advance milestone checkpoints, record hub locations, and push live tracking events.</p>
    </div>

    <!-- Status Filters -->
    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
        <a href="courier.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline' ?>">All</a>
        <a href="courier.php?status=Booking+Confirmed" class="btn btn-sm <?= $statusFilter === 'Booking Confirmed' ? 'btn-primary' : 'btn-outline' ?>">Confirmed</a>
        <a href="courier.php?status=Pickup+Assigned" class="btn btn-sm <?= $statusFilter === 'Pickup Assigned' ? 'btn-primary' : 'btn-outline' ?>">Pickup Assigned</a>
        <a href="courier.php?status=Picked+Up" class="btn btn-sm <?= $statusFilter === 'Picked Up' ? 'btn-primary' : 'btn-outline' ?>">Picked Up</a>
        <a href="courier.php?status=In+Transit" class="btn btn-sm <?= $statusFilter === 'In Transit' ? 'btn-primary' : 'btn-outline' ?>">In Transit</a>
        <a href="courier.php?status=Out+for+Delivery" class="btn btn-sm <?= $statusFilter === 'Out for Delivery' ? 'btn-primary' : 'btn-outline' ?>">Out for Delivery</a>
        <a href="courier.php?status=Delivered" class="btn btn-sm <?= $statusFilter === 'Delivered' ? 'btn-primary' : 'btn-outline' ?>">Delivered</a>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-boxes-packing" style="color: var(--accent);"></i> All Courier Consignments (<?= count($couriers) ?>)</h3>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking ID</th>
                    <th>Sender & Origin</th>
                    <th>Recipient & Destination</th>
                    <th>Parcel & Speed</th>
                    <th>Delivery Fee</th>
                    <th>Current Status</th>
                    <th style="min-width: 250px;">Advance Tracking Milestone</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($couriers)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--admin-muted); padding: 30px;">No courier shipments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($couriers as $c): ?>
                        <tr>
                            <td>
                                <a href="../tracking.php?code=<?= urlencode($c['tracking_code']) ?>" target="_blank" style="font-family: monospace; font-weight: 800; color: var(--primary); font-size: 15px;">
                                    <?= htmlspecialchars($c['tracking_code']) ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px;"></i>
                                </a><br>
                                <small style="color: var(--admin-muted);"><?= date('M d, Y', strtotime($c['created_at'])) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['sender_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($c['sender_phone']) ?></small><br>
                                <small><?= htmlspecialchars($c['pickup_address']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['receiver_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($c['receiver_phone']) ?></small><br>
                                <small><?= htmlspecialchars($c['delivery_address']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['parcel_type']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= number_format($c['parcel_weight'], 2) ?> kg</small><br>
                                <span class="status-pill status-confirmed" style="font-size: 10px;"><?= htmlspecialchars($c['delivery_option']) ?></span>
                            </td>
                            <td><strong><?= formatCurrency($c['delivery_fee']) ?></strong></td>
                            <td>
                                <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $c['current_status'])) ?>">
                                    <?= htmlspecialchars($c['current_status']) ?>
                                </span>
                            </td>
                            <td>
                                <form action="courier.php" method="POST" style="display: flex; flex-direction: column; gap: 6px; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid var(--admin-border);">
                                    <input type="hidden" name="action" value="update_tracking_milestone">
                                    <input type="hidden" name="courier_id" value="<?= $c['id'] ?>">

                                    <select name="status" class="form-select-sm" required>
                                        <option value="Booking Confirmed" <?= $c['current_status'] === 'Booking Confirmed' ? 'selected' : '' ?>>1. Booking Confirmed</option>
                                        <option value="Pickup Assigned" <?= $c['current_status'] === 'Pickup Assigned' ? 'selected' : '' ?>>2. Pickup Assigned</option>
                                        <option value="Picked Up" <?= $c['current_status'] === 'Picked Up' ? 'selected' : '' ?>>3. Picked Up</option>
                                        <option value="In Transit" <?= $c['current_status'] === 'In Transit' ? 'selected' : '' ?>>4. In Transit</option>
                                        <option value="Out for Delivery" <?= $c['current_status'] === 'Out for Delivery' ? 'selected' : '' ?>>5. Out for Delivery</option>
                                        <option value="Delivered" <?= $c['current_status'] === 'Delivered' ? 'selected' : '' ?>>6. Delivered</option>
                                        <option value="Cancelled" <?= $c['current_status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>

                                    <input type="text" name="location" class="form-select-sm" placeholder="Checkpoint Location (e.g. Central Sorting Hub)" required>
                                    <input type="text" name="remarks" class="form-select-sm" placeholder="Remarks (e.g. Dispatched to van)">

                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-location-arrow"></i> Advance Milestone</button>
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
