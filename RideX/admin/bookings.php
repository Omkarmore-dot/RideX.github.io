<?php
// admin/bookings.php
require_once __DIR__ . '/../config/db.php';
enforceAdminAreaAccess();
$adminTitle = "Ride Bookings Administration";

$db = getDB();

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $requestedPaymentStatus = trim($_POST['payment_status'] ?? '');

    $validStatuses = ['Confirmed', 'Driver Assigned', 'On The Way', 'Completed', 'Cancelled'];

    if ($bookingId && in_array($newStatus, $validStatuses, true)) {
        $check = $db->prepare("SELECT payment_status FROM bookings WHERE id = ? LIMIT 1");
        $check->bind_param("i", $bookingId);
        $check->execute();
        $current = $check->get_result()->fetch_assoc();

        if (!$current) {
            setFlash('error', "Booking not found.");
        } elseif ($requestedPaymentStatus === 'Paid' && $current['payment_status'] !== 'Paid') {
            setFlash('error', "Payment can only be marked Paid after successful Razorpay verification.");
        } elseif ($requestedPaymentStatus === 'Refunded' && $current['payment_status'] !== 'Paid') {
            setFlash('error', "Only a successfully paid booking can be marked Refunded.");
        } elseif ($current['payment_status'] !== 'Paid' && $newStatus !== 'Cancelled') {
            setFlash('error', "Unpaid bookings cannot be confirmed or dispatched.");
        } else {
            // Payment status is authoritative from Razorpay verification, except an
            // already-paid booking may still be marked Refunded by an administrator.
            $paymentStatus = ($requestedPaymentStatus === 'Refunded') ? 'Refunded' : $current['payment_status'];
            $stmt = $db->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newStatus, $paymentStatus, $bookingId);

            if ($stmt->execute()) {
                $stmtUser = $db->prepare("SELECT user_id, booking_code FROM bookings WHERE id = ?");
                $stmtUser->bind_param("i", $bookingId);
                $stmtUser->execute();
                $bk = $stmtUser->get_result()->fetch_assoc();

                if ($bk) {
                    createNotification(
                        $bk['user_id'],
                        "Ride Status: {$newStatus}",
                        "Your ride booking {$bk['booking_code']} status has been updated to {$newStatus}.",
                        "dashboard.php#bookings"
                    );
                }
                setFlash('success', "Booking #{$bookingId} updated successfully to '{$newStatus}'.");
            } else {
                setFlash('error', "Failed to update booking: " . $db->error);
            }
        }
    }

    header("Location: bookings.php");
    exit();
}

// Filter
$statusFilter = trim($_GET['status'] ?? '');
$sql = "SELECT b.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               v.name as vehicle_name, v.category, v.plate_number 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN vehicles v ON b.vehicle_id = v.id 
        WHERE 1=1";

if (!empty($statusFilter)) {
    $sql .= " AND b.status = '" . $db->real_escape_string($statusFilter) . "'";
}
$sql .= " ORDER BY b.created_at DESC";

$bookings = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--admin-text); margin: 0;">Ride Bookings Management</h1>
        <p style="color: var(--admin-muted); font-size: 14px; margin: 4px 0 0;">Monitor incoming rides, assign drivers, and manage statuses.</p>
    </div>

    <!-- Status Filters -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="bookings.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline' ?>">All</a>
        <a href="bookings.php?status=Confirmed" class="btn btn-sm <?= $statusFilter === 'Confirmed' ? 'btn-primary' : 'btn-outline' ?>">Confirmed</a>
        <a href="bookings.php?status=Driver+Assigned" class="btn btn-sm <?= $statusFilter === 'Driver Assigned' ? 'btn-primary' : 'btn-outline' ?>">Driver Assigned</a>
        <a href="bookings.php?status=On+The+Way" class="btn btn-sm <?= $statusFilter === 'On The Way' ? 'btn-primary' : 'btn-outline' ?>">On The Way</a>
        <a href="bookings.php?status=Completed" class="btn btn-sm <?= $statusFilter === 'Completed' ? 'btn-primary' : 'btn-outline' ?>">Completed</a>
        <a href="bookings.php?status=Cancelled" class="btn btn-sm <?= $statusFilter === 'Cancelled' ? 'btn-primary' : 'btn-outline' ?>">Cancelled</a>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-route" style="color: var(--admin-primary);"></i> All Ride Records (<?= count($bookings) ?>)</h3>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Booking Code</th>
                    <th>Customer Details</th>
                    <th>Vehicle Details</th>
                    <th>Pickup & Drop Route</th>
                    <th>Scheduled Time</th>
                    <th>Distance & Fare</th>
                    <th>Payment</th>
                    <th>Status & Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--admin-muted); padding: 30px;">No bookings found matching filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; color: var(--admin-primary); font-size: 15px;"><?= htmlspecialchars($b['booking_code']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= date('M d, Y', strtotime($b['created_at'])) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($b['customer_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);">
                                    <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($b['customer_phone']) ?><br>
                                    <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($b['customer_email']) ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($b['vehicle_name']) ?></strong><br>
                                <span class="status-pill status-confirmed" style="font-size: 11px;"><?= htmlspecialchars($b['category']) ?></span><br>
                                <small style="font-family: monospace;"><?= htmlspecialchars($b['plate_number']) ?></small>
                            </td>
                            <td>
                                <div style="font-size: 13px; max-width: 250px;">
                                    <strong>From:</strong> <?= htmlspecialchars($b['pickup_location']) ?><br>
                                    <strong>To:</strong> <?= htmlspecialchars($b['drop_location']) ?>
                                    <?php if (!empty($b['notes'])): ?>
                                        <p style="font-style: italic; color: var(--admin-muted); margin: 4px 0 0; font-size: 12px;">"<?= htmlspecialchars($b['notes']) ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong><?= date('M d, Y', strtotime($b['ride_date'])) ?></strong><br>
                                <span><?= date('h:i A', strtotime($b['ride_time'])) ?></span>
                            </td>
                            <td>
                                <strong><?= formatCurrency($b['total_fare']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= number_format($b['distance_km'], 1) ?> km</small>
                            </td>
                            <td>
                                <span class="status-pill <?= $b['payment_status'] === 'Paid' ? 'status-completed' : 'status-pending' ?>">
                                    <?= htmlspecialchars($b['payment_status']) ?> (<?= htmlspecialchars($b['payment_method']) ?>)
                                </span>
                            </td>
                            <td>
                                <form action="bookings.php" method="POST" style="display: flex; flex-direction: column; gap: 6px;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    
                                    <select name="status" class="form-select-sm">
                                        <option value="Confirmed" <?= $b['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="Driver Assigned" <?= $b['status'] === 'Driver Assigned' ? 'selected' : '' ?>>Driver Assigned</option>
                                        <option value="On The Way" <?= $b['status'] === 'On The Way' ? 'selected' : '' ?>>On The Way</option>
                                        <option value="Completed" <?= $b['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Cancelled" <?= $b['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>

                                    <select name="payment_status" class="form-select-sm">
                                        <option value="Paid" <?= $b['payment_status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="Pending" <?= $b['payment_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Refunded" <?= $b['payment_status'] === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
                                    </select>

                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-save"></i> Save</button>
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
