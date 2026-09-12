<?php
// dashboard.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "User Dashboard";

requireLogin();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$db = getDB();

// Handle Mark All Notifications as Read action
if (isset($_GET['action']) && $_GET['action'] === 'read_all_notifs') {
    $db->query("UPDATE notifications SET is_read = 1 WHERE user_id = " . $userId);
    setFlash('success', 'All notifications marked as read.');
    header("Location: dashboard.php#notifications");
    exit();
}

// 1. Fetch Ride Bookings
$stmtRides = $db->prepare("SELECT b.*, v.name as vehicle_name, v.category, v.image_url,
                                  (SELECT COUNT(*) FROM ratings r WHERE r.booking_id = b.id AND r.booking_type = 'ride') as has_rated
                           FROM bookings b 
                           JOIN vehicles v ON b.vehicle_id = v.id 
                           WHERE b.user_id = ? 
                           ORDER BY b.created_at DESC");
$stmtRides->bind_param("i", $userId);
$stmtRides->execute();
$rideBookings = $stmtRides->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Fetch Vehicle Rentals
$stmtRentals = $db->prepare("SELECT r.*, v.name as vehicle_name, v.category, v.image_url,
                                    (SELECT COUNT(*) FROM ratings rt WHERE rt.booking_id = r.id AND rt.booking_type = 'rental') as has_rated
                             FROM rentals r 
                             JOIN vehicles v ON r.vehicle_id = v.id 
                             WHERE r.user_id = ? 
                             ORDER BY r.created_at DESC");
$stmtRentals->bind_param("i", $userId);
$stmtRentals->execute();
$rentals = $stmtRentals->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Fetch Courier Shipments
$stmtCouriers = $db->prepare("SELECT * FROM courier_bookings WHERE user_id = ? ORDER BY created_at DESC");
$stmtCouriers->bind_param("i", $userId);
$stmtCouriers->execute();
$couriers = $stmtCouriers->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Fetch Notifications
$stmtNotifs = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$stmtNotifs->bind_param("i", $userId);
$stmtNotifs->execute();
$notifications = $stmtNotifs->get_result()->fetch_all(MYSQLI_ASSOC);

// Metrics
$totalBookingsCount = count($rideBookings);
$activeRidesCount = 0;
$completedRidesCount = 0;
foreach ($rideBookings as $b) {
    if (in_array($b['status'], ['Confirmed', 'Driver Assigned', 'On The Way'])) {
        $activeRidesCount++;
    } elseif ($b['status'] === 'Completed') {
        $completedRidesCount++;
    }
}

$activeRentalsCount = 0;
foreach ($rentals as $r) {
    if ($r['status'] === 'Active') {
        $activeRentalsCount++;
    }
}

$activeCouriersCount = 0;
foreach ($couriers as $c) {
    if ($c['current_status'] !== 'Delivered' && $c['current_status'] !== 'Cancelled') {
        $activeCouriersCount++;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <!-- Dashboard Top Header Banner -->
    <div class="card" style="margin-bottom: 30px; background: linear-gradient(135deg, #1e1b4b, #0f172a); color: #fff; padding: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 800;">
                    <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <h1 style="font-size: 24px; font-weight: 800; margin-bottom: 4px; color: #fff;">Welcome back, <?= htmlspecialchars($currentUser['full_name']) ?>!</h1>
                    <p style="color: #cbd5e1; font-size: 14px; margin-bottom: 0;">
                        <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($currentUser['email']) ?> &nbsp;|&nbsp; 
                        <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($currentUser['phone']) ?> &nbsp;|&nbsp; 
                        <span class="status-pill status-completed" style="font-size: 11px;">Active Member</span>
                    </p>
                </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="booking.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-taxi"></i> New Ride</a>
                <a href="rental.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-key"></i> New Rental</a>
                <a href="courier.php" class="btn btn-accent btn-sm"><i class="fa-solid fa-box"></i> Send Courier</a>
            </div>
        </div>
    </div>

    <!-- Live Overview Statistics Grid -->
    <div class="dash-stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-route"></i></div>
            <div class="stat-content">
                <h3><?= $totalBookingsCount ?></h3>
                <p>Total Rides</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-car"></i></div>
            <div class="stat-content">
                <h3><?= $activeRidesCount ?></h3>
                <p>Active Rides</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-key"></i></div>
            <div class="stat-content">
                <h3><?= $activeRentalsCount ?></h3>
                <p>Active Rentals</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-truck-ramp-box"></i></div>
            <div class="stat-content">
                <h3><?= $activeCouriersCount ?></h3>
                <p>In-Transit Parcels</p>
            </div>
        </div>
    </div>

    <!-- Dashboard Tabs Interface -->
    <div class="card tab-wrapper">
        <div class="dash-tabs">
            <button class="dash-tab-btn active" data-tab="bookings"><i class="fa-solid fa-taxi"></i> Ride Bookings (<?= count($rideBookings) ?>)</button>
            <button class="dash-tab-btn" data-tab="rentals"><i class="fa-solid fa-key"></i> Vehicle Rentals (<?= count($rentals) ?>)</button>
            <button class="dash-tab-btn" data-tab="courier"><i class="fa-solid fa-box"></i> Courier Deliveries (<?= count($couriers) ?>)</button>
            <button class="dash-tab-btn" data-tab="notifications">
                <i class="fa-solid fa-bell"></i> Notifications 
                <?php if ($unreadNotifs > 0): ?>
                    <span class="status-pill status-cancelled" style="padding: 2px 6px; font-size: 11px;"><?= $unreadNotifs ?> new</span>
                <?php endif; ?>
            </button>
        </div>

        <!-- TAB 1: Ride Bookings -->
        <div class="tab-content active" id="bookings">
            <?php if (empty($rideBookings)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fa-solid fa-taxi" style="font-size: 44px; color: var(--text-muted); margin-bottom: 12px;"></i>
                    <h3>No Ride Bookings Yet</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">Ready to hit the road? Book your first trip in seconds.</p>
                    <a href="booking.php" class="btn btn-primary">Book a Ride Now</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Booking Code</th>
                                <th>Vehicle</th>
                                <th>Route</th>
                                <th>Date & Time</th>
                                <th>Distance</th>
                                <th>Total Fare</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rideBookings as $b): ?>
                                <tr>
                                    <td><strong style="font-family: monospace; color: var(--primary);"><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['vehicle_name']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($b['category']) ?></small>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px;">
                                            <span style="color: var(--primary);"><i class="fa-solid fa-circle-dot"></i></span> <?= htmlspecialchars($b['pickup_location']) ?><br>
                                            <span style="color: var(--accent);"><i class="fa-solid fa-location-dot"></i></span> <?= htmlspecialchars($b['drop_location']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= date('M d, Y', strtotime($b['ride_date'])) ?><br>
                                        <small style="color: var(--text-muted);"><?= date('h:i A', strtotime($b['ride_time'])) ?></small>
                                    </td>
                                    <td><?= number_format($b['distance_km'], 1) ?> km</td>
                                    <td>
                                        <strong><?= formatCurrency($b['total_fare']) ?></strong><br>
                                        <span class="status-pill status-completed" style="font-size: 10px;"><?= htmlspecialchars($b['payment_method']) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $b['status'])) ?>">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($b['status'] === 'Completed'): ?>
                                            <?php if ($b['has_rated'] > 0): ?>
                                                <span style="font-size: 12px; color: var(--accent); font-weight: 600;"><i class="fa-solid fa-check-double"></i> Rated</span>
                                            <?php else: ?>
                                                <a href="rate_booking.php?type=ride&id=<?= $b['id'] ?>&vehicle_id=<?= $b['vehicle_id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fa-solid fa-star"></i> Rate Ride
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--text-muted);"><i class="fa-solid fa-clock"></i> Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Vehicle Rentals -->
        <div class="tab-content" id="rentals">
            <?php if (empty($rentals)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fa-solid fa-key" style="font-size: 44px; color: var(--text-muted); margin-bottom: 12px;"></i>
                    <h3>No Rental Reservations</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">Rent a car, bike, or hauler for self-driving with transparent daily rates.</p>
                    <a href="rental.php" class="btn btn-secondary">Explore Rentals</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rental Code</th>
                                <th>Vehicle</th>
                                <th>Period</th>
                                <th>Duration</th>
                                <th>Delivery Address</th>
                                <th>License Number</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rentals as $r): ?>
                                <tr>
                                    <td><strong style="font-family: monospace; color: var(--secondary);"><?= htmlspecialchars($r['rental_code']) ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($r['vehicle_name']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($r['category']) ?></small>
                                    </td>
                                    <td>
                                        <?= date('M d', strtotime($r['start_date'])) ?> – <?= date('M d, Y', strtotime($r['end_date'])) ?>
                                    </td>
                                    <td><?= $r['total_days'] ?> Days</td>
                                    <td><small><?= htmlspecialchars($r['pickup_address']) ?></small></td>
                                    <td><span style="font-family: monospace;"><?= htmlspecialchars($r['driving_license_number']) ?></span></td>
                                    <td><strong><?= formatCurrency($r['total_amount']) ?></strong></td>
                                    <td>
                                        <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $r['status'])) ?>">
                                            <?= htmlspecialchars($r['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($r['status'] === 'Completed'): ?>
                                            <?php if ($r['has_rated'] > 0): ?>
                                                <span style="font-size: 12px; color: var(--accent); font-weight: 600;"><i class="fa-solid fa-check-double"></i> Rated</span>
                                            <?php else: ?>
                                                <a href="rate_booking.php?type=rental&id=<?= $r['id'] ?>&vehicle_id=<?= $r['vehicle_id'] ?>" class="btn btn-sm btn-secondary">
                                                    <i class="fa-solid fa-star"></i> Rate Vehicle
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--text-muted);"><i class="fa-solid fa-circle-check"></i> Reserved</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 3: Courier Deliveries -->
        <div class="tab-content" id="courier">
            <?php if (empty($couriers)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fa-solid fa-box" style="font-size: 44px; color: var(--text-muted); margin-bottom: 12px;"></i>
                    <h3>No Courier Deliveries</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">Dispatch packages across city hubs with instant live tracking telemetry.</p>
                    <a href="courier.php" class="btn btn-accent">Dispatch a Package</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking Code</th>
                                <th>Receiver</th>
                                <th>Destination</th>
                                <th>Parcel Details</th>
                                <th>Speed Option</th>
                                <th>Fee</th>
                                <th>Live Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($couriers as $c): ?>
                                <tr>
                                    <td><strong style="font-family: monospace; color: var(--primary);"><?= htmlspecialchars($c['tracking_code']) ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($c['receiver_name']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($c['receiver_phone']) ?></small>
                                    </td>
                                    <td><small><?= htmlspecialchars($c['delivery_address']) ?></small></td>
                                    <td>
                                        <?= htmlspecialchars($c['parcel_type']) ?><br>
                                        <small style="color: var(--text-muted);"><?= number_format($c['parcel_weight'], 2) ?> kg</small>
                                    </td>
                                    <td><span class="status-pill status-confirmed"><?= htmlspecialchars($c['delivery_option']) ?></span></td>
                                    <td><strong><?= formatCurrency($c['delivery_fee']) ?></strong></td>
                                    <td>
                                        <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $c['current_status'])) ?>">
                                            <i class="fa-solid fa-truck"></i> <?= htmlspecialchars($c['current_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="tracking.php?code=<?= urlencode($c['tracking_code']) ?>" class="btn btn-sm btn-primary">
                                            <i class="fa-solid fa-location-crosshairs"></i> Track
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 4: Notifications Center -->
        <div class="tab-content" id="notifications">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                <h3 style="font-size: 18px; font-weight: 700;">System & Booking Notifications</h3>
                <?php if ($unreadNotifs > 0): ?>
                    <a href="dashboard.php?action=read_all_notifs" class="btn btn-sm btn-outline"><i class="fa-solid fa-check-double"></i> Mark All as Read</a>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <p style="color: var(--text-muted); font-style: italic; padding: 20px 0;">You have no notifications at this time.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($notifications as $n): ?>
                        <div style="padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid <?= $n['is_read'] ? 'var(--border)' : '#818cf8' ?>; background: <?= $n['is_read'] ? '#ffffff' : '#f5f3ff' ?>; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <?php if (!$n['is_read']): ?>
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary);"></span>
                                    <?php endif; ?>
                                    <strong style="font-size: 14px; color: var(--dark);"><?= htmlspecialchars($n['title']) ?></strong>
                                    <span style="font-size: 11px; color: var(--text-muted);">&bull; <?= date('M d, Y - h:i A', strtotime($n['created_at'])) ?></span>
                                </div>
                                <p style="font-size: 13px; color: var(--text-muted); margin: 0;"><?= htmlspecialchars($n['message']) ?></p>
                            </div>

                            <?php if (!empty($n['link'])): ?>
                                <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-outline" style="white-space: nowrap;">View Details</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
