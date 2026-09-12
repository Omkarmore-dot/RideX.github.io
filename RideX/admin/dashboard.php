<?php
// admin/dashboard.php
require_once __DIR__ . '/../config/db.php';
enforceAdminAreaAccess();

// Early access guard — block before any DB queries run
// (admin_header.php also enforces this, but we guard here first for security)
if (isLoggedIn() && !isAdminLoggedIn()) {
    // Regular user trying to access admin — admin_header will show "No Access" page
    // Just include it and let it handle the response
    $adminTitle = "Dashboard Overview";
    include __DIR__ . '/includes/admin_header.php';
    exit();
}
if (!isAdminLoggedIn()) {
    $_SESSION['flash_error'] = "Admin access required. Please authenticate.";
    header("Location: login.php");
    exit();
}

$adminTitle = "Dashboard Overview";
$db = getDB();

// Handle Quick Ride Status Update via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ride_status') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    $validStatuses = ['Confirmed', 'Driver Assigned', 'On The Way', 'Completed', 'Cancelled'];
    if ($bookingId && in_array($newStatus, $validStatuses, true)) {
        $check = $db->prepare("SELECT payment_status FROM bookings WHERE id = ? LIMIT 1");
        $check->bind_param("i", $bookingId);
        $check->execute();
        $current = $check->get_result()->fetch_assoc();

        if (!$current) {
            setFlash('error', "Booking not found.");
        } elseif ($current['payment_status'] !== 'Paid' && $newStatus !== 'Cancelled') {
            setFlash('error', "Unpaid bookings cannot be confirmed or dispatched.");
        } else {
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $bookingId);

            if ($stmt->execute()) {
                $stmtUser = $db->prepare("SELECT user_id, booking_code FROM bookings WHERE id = ?");
                $stmtUser->bind_param("i", $bookingId);
                $stmtUser->execute();
                $bk = $stmtUser->get_result()->fetch_assoc();

                if ($bk) {
                    createNotification(
                        $bk['user_id'],
                        "Ride Status: {$newStatus}",
                        "Your ride booking {$bk['booking_code']} has been marked as: {$newStatus}.",
                        "dashboard.php#bookings"
                    );
                }
                setFlash('success', "Booking #{$bookingId} status updated to '{$newStatus}'.");
            } else {
                setFlash('error', "Failed to update booking: " . $db->error);
            }
        }
    }

    header("Location: dashboard.php");
    exit();
}

// Handle Quick Courier Status Update via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_courier_status') {
    $courierId = (int)($_POST['courier_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $location = trim($_POST['location'] ?? 'RideX Logistics Center');
    $remarks = trim($_POST['remarks'] ?? 'Milestone updated by administrative dispatch.');

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
            addCourierTrackingEvent($courierId, $newStatus, $location, $remarks);

            $stmtC = $db->prepare("SELECT user_id, tracking_code FROM courier_bookings WHERE id = ?");
            $stmtC->bind_param("i", $courierId);
            $stmtC->execute();
            $cr = $stmtC->get_result()->fetch_assoc();

            if ($cr && $cr['user_id']) {
                createNotification(
                    $cr['user_id'],
                    "Courier {$newStatus}",
                    "Shipment {$cr['tracking_code']} status changed to {$newStatus}.",
                    "tracking.php?code={$cr['tracking_code']}"
                );
            }
            setFlash('success', "Courier #{$courierId} tracking advanced to '{$newStatus}'.");
        }
    }

    header("Location: dashboard.php");
    exit();
}

// Fetch System Metrics from MySQL
$totalUsers = (int)($db->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'] ?? 0);
$totalVehicles = (int)($db->query("SELECT COUNT(*) as c FROM vehicles")->fetch_assoc()['c'] ?? 0);
$totalRides = (int)($db->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'] ?? 0);
$totalRentals = (int)($db->query("SELECT COUNT(*) as c FROM rentals")->fetch_assoc()['c'] ?? 0);
$activeCouriers = (int)($db->query("SELECT COUNT(*) as c FROM courier_bookings WHERE current_status NOT IN ('Delivered', 'Cancelled')")->fetch_assoc()['c'] ?? 0);

// Total Revenue Calculation (Rides + Rentals + Couriers)
$rideRev = (float)($db->query("SELECT SUM(total_fare) as s FROM bookings WHERE payment_status = 'Paid' AND status != 'Cancelled'")->fetch_assoc()['s'] ?? 0);
$rentalRev = (float)($db->query("SELECT SUM(total_amount) as s FROM rentals WHERE payment_status = 'Paid' AND status != 'Cancelled'")->fetch_assoc()['s'] ?? 0);
$courierRev = (float)($db->query("SELECT SUM(delivery_fee) as s FROM courier_bookings WHERE payment_status = 'Paid' AND current_status != 'Cancelled'")->fetch_assoc()['s'] ?? 0);
$totalRevenue = $rideRev + $rentalRev + $courierRev;

// Fetch Recent Ride Bookings (limit 5)
$recentRides = $db->query("SELECT b.*, u.full_name as customer_name, v.name as vehicle_name, v.category 
                           FROM bookings b 
                           JOIN users u ON b.user_id = u.id 
                           JOIN vehicles v ON b.vehicle_id = v.id 
                           ORDER BY b.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Fetch Recent Courier Deliveries (limit 5)
$recentCouriers = $db->query("SELECT * FROM courier_bookings ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<!-- Metric Cards Overview -->
<div class="admin-metrics">
    <div class="metric-card">
        <div>
            <h4>Total Revenue</h4>
            <h2><?= formatCurrency($totalRevenue) ?></h2>
        </div>
        <div class="metric-icon" style="background: #dcfce7; color: #16a34a;"><i class="fa-solid fa-dollar-sign"></i></div>
    </div>
    <div class="metric-card">
        <div>
            <h4>Ride Bookings</h4>
            <h2><?= $totalRides ?></h2>
        </div>
        <div class="metric-icon" style="background: #e0f2fe; color: #0284c7;"><i class="fa-solid fa-taxi"></i></div>
    </div>
    <div class="metric-card">
        <div>
            <h4>Vehicle Rentals</h4>
            <h2><?= $totalRentals ?></h2>
        </div>
        <div class="metric-icon" style="background: #ede9fe; color: #7c3aed;"><i class="fa-solid fa-key"></i></div>
    </div>
    <div class="metric-card">
        <div>
            <h4>Active Couriers</h4>
            <h2><?= $activeCouriers ?></h2>
        </div>
        <div class="metric-icon" style="background: #fef3c7; color: #d97706;"><i class="fa-solid fa-truck-fast"></i></div>
    </div>
    <div class="metric-card">
        <div>
            <h4>Fleet Vehicles</h4>
            <h2><?= $totalVehicles ?></h2>
        </div>
        <div class="metric-icon" style="background: #f1f5f9; color: #475569;"><i class="fa-solid fa-car"></i></div>
    </div>
    <div class="metric-card">
        <div>
            <h4>Registered Users</h4>
            <h2><?= $totalUsers ?></h2>
        </div>
        <div class="metric-icon" style="background: #fee2e2; color: #dc2626;"><i class="fa-solid fa-users"></i></div>
    </div>
</div>

<!-- Quick Shortcuts Toolbar -->
<div class="admin-card" style="padding: 16px 24px; margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <span style="font-weight: 700; font-size: 14px; color: var(--admin-text);"><i class="fa-solid fa-bolt"></i> Quick Operations:</span>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="vehicles.php?action=add" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> Add New Vehicle</a>
            <a href="bookings.php" class="btn btn-sm btn-outline"><i class="fa-solid fa-taxi"></i> All Ride Bookings</a>
            <a href="rentals.php" class="btn btn-sm btn-outline"><i class="fa-solid fa-key"></i> All Rentals</a>
            <a href="courier.php" class="btn btn-sm btn-outline"><i class="fa-solid fa-boxes-packing"></i> Courier Dispatches</a>
            <a href="users.php" class="btn btn-sm btn-outline"><i class="fa-solid fa-users"></i> User Accounts</a>
        </div>
    </div>
</div>

<!-- Recent Ride Bookings with Inline Status Management -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-taxi" style="color: var(--primary);"></i> Recent Ride Bookings (Live Status Dispatch)</h3>
        <a href="bookings.php" class="btn btn-sm btn-outline">View All (<?= $totalRides ?>)</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Booking Code</th>
                    <th>Customer</th>
                    <th>Vehicle</th>
                    <th>Trip Route</th>
                    <th>Schedule</th>
                    <th>Fare</th>
                    <th>Current Status</th>
                    <th>Update Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentRides)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--admin-muted);">No ride bookings recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentRides as $r): ?>
                        <tr>
                            <td><strong style="font-family: monospace; color: var(--primary);"><?= htmlspecialchars($r['booking_code']) ?></strong></td>
                            <td><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($r['vehicle_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= htmlspecialchars($r['category']) ?></small>
                            </td>
                            <td>
                                <small>
                                    <strong>From:</strong> <?= htmlspecialchars($r['pickup_location']) ?><br>
                                    <strong>To:</strong> <?= htmlspecialchars($r['drop_location']) ?> (<?= $r['distance_km'] ?> km)
                                </small>
                            </td>
                            <td>
                                <?= date('M d', strtotime($r['ride_date'])) ?> &bull; <?= date('h:i A', strtotime($r['ride_time'])) ?>
                            </td>
                            <td><strong><?= formatCurrency($r['total_fare']) ?></strong></td>
                            <td>
                                <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $r['status'])) ?>">
                                    <?= htmlspecialchars($r['status']) ?>
                                </span>
                            </td>
                            <td>
                                <form action="dashboard.php" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                    <input type="hidden" name="action" value="update_ride_status">
                                    <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
                                    <select name="status" class="form-select-sm">
                                        <option value="Confirmed" <?= $r['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="Driver Assigned" <?= $r['status'] === 'Driver Assigned' ? 'selected' : '' ?>>Driver Assigned</option>
                                        <option value="On The Way" <?= $r['status'] === 'On The Way' ? 'selected' : '' ?>>On The Way</option>
                                        <option value="Completed" <?= $r['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Cancelled" <?= $r['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary" title="Save Status Change"><i class="fa-solid fa-check"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Courier Deliveries with Milestone Tracker Updater -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-truck-fast" style="color: var(--accent);"></i> Recent Courier Shipments (Milestone Progression)</h3>
        <a href="courier.php" class="btn btn-sm btn-outline">Manage Couriers</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking Code</th>
                    <th>Sender &rarr; Receiver</th>
                    <th>Parcel Type</th>
                    <th>Weight</th>
                    <th>Option</th>
                    <th>Current Status</th>
                    <th>Advance Milestone</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentCouriers)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--admin-muted);">No courier deliveries recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentCouriers as $c): ?>
                        <tr>
                            <td>
                                <a href="../tracking.php?code=<?= urlencode($c['tracking_code']) ?>" target="_blank" style="font-family: monospace; font-weight: 700;">
                                    <?= htmlspecialchars($c['tracking_code']) ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px;"></i>
                                </a>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['sender_name']) ?></strong><br>
                                <small style="color: var(--admin-muted);">&rarr; <?= htmlspecialchars($c['receiver_name']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($c['parcel_type']) ?></td>
                            <td><?= number_format($c['parcel_weight'], 2) ?> kg</td>
                            <td><span class="status-pill status-confirmed"><?= htmlspecialchars($c['delivery_option']) ?></span></td>
                            <td>
                                <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $c['current_status'])) ?>">
                                    <?= htmlspecialchars($c['current_status']) ?>
                                </span>
                            </td>
                            <td>
                                <form action="dashboard.php" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                    <input type="hidden" name="action" value="update_courier_status">
                                    <input type="hidden" name="courier_id" value="<?= $c['id'] ?>">
                                    <select name="status" class="form-select-sm">
                                        <option value="Booking Confirmed" <?= $c['current_status'] === 'Booking Confirmed' ? 'selected' : '' ?>>Booking Confirmed</option>
                                        <option value="Pickup Assigned" <?= $c['current_status'] === 'Pickup Assigned' ? 'selected' : '' ?>>Pickup Assigned</option>
                                        <option value="Picked Up" <?= $c['current_status'] === 'Picked Up' ? 'selected' : '' ?>>Picked Up</option>
                                        <option value="In Transit" <?= $c['current_status'] === 'In Transit' ? 'selected' : '' ?>>In Transit</option>
                                        <option value="Out for Delivery" <?= $c['current_status'] === 'Out for Delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                        <option value="Delivered" <?= $c['current_status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="Cancelled" <?= $c['current_status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary" title="Update Milestone & Append Log"><i class="fa-solid fa-check"></i></button>
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
