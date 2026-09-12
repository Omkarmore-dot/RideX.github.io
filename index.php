<?php
// index.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System";

$db = getDB();

// Fetch category vehicle counts
$categoryCounts = [];
$catQuery = $db->query("SELECT category, COUNT(*) as total FROM vehicles GROUP BY category");
if ($catQuery) {
    while ($row = $catQuery->fetch_assoc()) {
        $categoryCounts[$row['category']] = $row['total'];
    }
}

// Fetch featured vehicles (limit 6)
$featuredVehicles = [];
$featQuery = $db->query("SELECT * FROM vehicles WHERE status = 'available' ORDER BY id ASC LIMIT 6");
if ($featQuery) {
    while ($row = $featQuery->fetch_assoc()) {
        $featuredVehicles[] = $row;
    }
}

// Fetch available vehicles for quick booking dropdowns
$availableVehicles = [];
$availQuery = $db->query("SELECT id, name, category, base_fare, price_per_km, price_per_day FROM vehicles WHERE status = 'available' ORDER BY category ASC, name ASC");
if ($availQuery) {
    while ($row = $availQuery->fetch_assoc()) {
        $availableVehicles[] = $row;
    }
}

// Fetch recent customer reviews
$recentReviews = [];
$revQuery = $db->query("SELECT r.*, u.full_name, v.name as vehicle_name, v.category 
                        FROM ratings r 
                        JOIN users u ON r.user_id = u.id 
                        JOIN vehicles v ON r.vehicle_id = v.id 
                        ORDER BY r.created_at DESC LIMIT 3");
if ($revQuery) {
    while ($row = $revQuery->fetch_assoc()) {
        $recentReviews[] = $row;
    }
}

// Aggregate System Metrics
$totalRides = $db->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'] ?? 0;
$totalRentals = $db->query("SELECT COUNT(*) as c FROM rentals")->fetch_assoc()['c'] ?? 0;
$totalFleet = $db->query("SELECT COUNT(*) as c FROM vehicles")->fetch_assoc()['c'] ?? 0;
$totalCouriers = $db->query("SELECT COUNT(*) as c FROM courier_bookings")->fetch_assoc()['c'] ?? 0;

$categoriesList = getVehicleCategories();

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section with Interactive 3-in-1 Quick Booking Bar -->
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div>
                <div class="hero-tag">
                    <i class="fa-solid fa-sparkles"></i> RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System
                </div>
                <h1>Smart Ride Booking, Rentals & <span>Swift Courier</span></h1>
                <p>Experience seamless mobility across 7 vehicle categories — from high-speed commuter bikes and luxury sedans to heavy-duty haulers and guaranteed courier delivery.</p>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="booking.php" class="btn btn-lg btn-primary"><i class="fa-solid fa-car"></i> Book a Ride</a>
                    <a href="rental.php" class="btn btn-lg btn-outline" style="color: #fff; border-color: rgba(255,255,255,0.3);"><i class="fa-solid fa-key"></i> Rent Self-Drive</a>
                </div>
            </div>

            <!-- Hero Quick Launcher Widget -->
            <div class="hero-booking-widget tab-wrapper">
                <div class="tab-nav">
                    <button class="tab-btn active" data-tab="tab-ride"><i class="fa-solid fa-taxi"></i> Ride</button>
                    <button class="tab-btn" data-tab="tab-rent"><i class="fa-solid fa-car"></i> Rent</button>
                    <button class="tab-btn" data-tab="tab-courier"><i class="fa-solid fa-box"></i> Courier</button>
                </div>

                <!-- Tab 1: Quick Ride -->
                <div class="tab-content active" id="tab-ride">
                    <form action="booking.php" method="GET">
                        <div class="form-group">
                            <label><i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Pickup Location</label>
                            <input type="text" name="pickup" class="form-control" placeholder="e.g. Central City Station" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-location-crosshairs" style="color: var(--accent);"></i> Dropoff Destination</label>
                            <input type="text" name="drop" class="form-control" placeholder="e.g. Skyline Airport Terminal" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Vehicle Type</label>
                                <select name="vehicle_id" class="form-control" required>
                                    <option value="">-- Select Ride --</option>
                                    <?php foreach ($availableVehicles as $v): ?>
                                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?> (<?= $v['category'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Distance (KM)</label>
                                <input type="number" name="distance" class="form-control" placeholder="10" min="1" step="0.5" value="10">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-arrow-right"></i> Calculate Fare & Book</button>
                    </form>
                </div>

                <!-- Tab 2: Quick Rental -->
                <div class="tab-content" id="tab-rent">
                    <form action="rental.php" method="GET">
                        <div class="form-group">
                            <label>Choose Rental Vehicle</label>
                            <select name="vehicle_id" class="form-control" required>
                                <option value="">-- Choose Vehicle --</option>
                                <?php foreach ($availableVehicles as $v): ?>
                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?> - <?= formatCurrency($v['price_per_day']) ?>/day</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-block"><i class="fa-solid fa-calendar-check"></i> Check Rental Availability</button>
                    </form>
                </div>

                <!-- Tab 3: Quick Courier Tracking -->
                <div class="tab-content" id="tab-courier">
                    <form action="tracking.php" method="GET">
                        <div class="form-group">
                            <label><i class="fa-solid fa-barcode"></i> Have a Tracking Code?</label>
                            <input type="text" name="code" class="form-control" placeholder="Enter Tracking Code (e.g. RX-EXP-88901)" required>
                        </div>
                        <button type="submit" class="btn btn-accent btn-block"><i class="fa-solid fa-magnifying-glass"></i> Track Shipment Now</button>
                    </form>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="courier.php" style="font-size: 13px; font-weight: 600;"><i class="fa-solid fa-plus"></i> Or Book a New Courier Parcel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Vehicle Categories Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Diverse Mobility Options</span>
            <h2 class="section-title">Explore by Vehicle Category</h2>
            <p class="section-desc">From daily solo rides to group trips and commercial parcel transfers, we have the ideal vehicle for every mission.</p>
        </div>

        <div class="categories-grid">
            <?php foreach ($categoriesList as $catName => $catData): ?>
                <a href="vehicles.php?category=<?= urlencode($catName) ?>" class="category-card">
                    <div class="cat-icon">
                        <i class="fa-solid <?= $catData['icon'] ?>"></i>
                    </div>
                    <h3><?= htmlspecialchars($catName) ?></h3>
                    <span><?= $categoryCounts[$catName] ?? 0 ?> Available</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Fleet Section -->
<section class="section" style="background-color: #ffffff; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; flex-wrap: wrap; gap: 15px;">
            <div>
                <span class="section-subtitle">Verified & Clean Fleet</span>
                <h2 class="section-title" style="margin-bottom: 0;">Featured Vehicles Ready To Go</h2>
            </div>
            <a href="vehicles.php" class="btn btn-outline">View All Fleet (<?= $totalFleet ?>) <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="vehicle-grid">
            <?php foreach ($featuredVehicles as $v): ?>
                <div class="vehicle-card">
                    <div class="vehicle-img-wrap">
                        <img src="<?= htmlspecialchars($v['image_url']) ?>" alt="<?= htmlspecialchars($v['name']) ?>" loading="lazy">
                        <span class="badge-cat"><?= htmlspecialchars($v['category']) ?></span>
                        <span class="badge-status badge-<?= strtolower($v['status']) ?>"><?= htmlspecialchars($v['status']) ?></span>
                    </div>
                    <div class="vehicle-body">
                        <h3><?= htmlspecialchars($v['name']) ?></h3>
                        <div class="vehicle-meta">
                            <span><i class="fa-solid fa-car"></i> <?= htmlspecialchars($v['brand']) ?></span>
                            <span><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($v['model_year']) ?></span>
                        </div>

                        <div class="vehicle-specs">
                            <div class="spec-item">
                                <span>Seats</span>
                                <strong><?= $v['seating_capacity'] ?></strong>
                            </div>
                            <div class="spec-item">
                                <span>Fuel</span>
                                <strong><?= $v['fuel_type'] ?></strong>
                            </div>
                            <div class="spec-item">
                                <span>Gear</span>
                                <strong><?= $v['transmission'] ?></strong>
                            </div>
                        </div>

                        <div class="vehicle-pricing">
                            <div class="price-item">
                                Ride Rate
                                <strong><?= formatCurrency($v['price_per_km']) ?>/km</strong>
                            </div>
                            <div class="price-item" style="text-align: right;">
                                Rental Rate
                                <strong><?= formatCurrency($v['price_per_day']) ?>/day</strong>
                            </div>
                        </div>

                        <div class="vehicle-actions">
                            <a href="booking.php?vehicle_id=<?= $v['id'] ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-taxi"></i> Book</a>
                            <a href="rental.php?vehicle_id=<?= $v['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-key"></i> Rent</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How RideX Works -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Effortless Experience</span>
            <h2 class="section-title">How RideX Operates</h2>
            <p class="section-desc">Get on the road or send a package in 4 simple connected steps.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 24px;">
            <div class="card" style="text-align: center;">
                <div class="stat-icon blue" style="margin: 0 auto 16px;">
                    <i class="fa-solid fa-hand-pointer"></i>
                </div>
                <h3 style="font-size: 18px; margin-bottom: 8px;">1. Choose Service</h3>
                <p style="font-size: 14px; color: var(--text-muted);">Pick instant ride hailing, multi-day self-drive rental, or courier delivery.</p>
            </div>
            <div class="card" style="text-align: center;">
                <div class="stat-icon green" style="margin: 0 auto 16px;">
                    <i class="fa-solid fa-calculator"></i>
                </div>
                <h3 style="font-size: 18px; margin-bottom: 8px;">2. Transparent Pricing</h3>
                <p style="font-size: 14px; color: var(--text-muted);">Automatic fare formulas calculate distance and duration with zero hidden charges.</p>
            </div>
            <div class="card" style="text-align: center;">
                <div class="stat-icon purple" style="margin: 0 auto 16px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3 style="font-size: 18px; margin-bottom: 8px;">3. Instant Booking</h3>
                <p style="font-size: 14px; color: var(--text-muted);">Receive your verified Booking Code or Live Tracking ID right away.</p>
            </div>
            <div class="card" style="text-align: center;">
                <div class="stat-icon amber" style="margin: 0 auto 16px;">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <h3 style="font-size: 18px; margin-bottom: 8px;">4. Real-Time Tracking</h3>
                <p style="font-size: 14px; color: var(--text-muted);">Monitor delivery milestones, manage trips in your User Dashboard, and leave ratings.</p>
            </div>
        </div>
    </div>
</section>

<!-- System Statistics Metrics Banner -->
<section style="background: linear-gradient(135deg, #1e1b4b, #0f172a); color: #fff; padding: 50px 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 24px; text-align: center;">
            <div>
                <h2 style="font-size: 40px; font-weight: 800; color: #38bdf8;"><?= $totalRides + 140 ?>+</h2>
                <p style="color: #cbd5e1; font-size: 14px; font-weight: 600;">Rides Completed</p>
            </div>
            <div>
                <h2 style="font-size: 40px; font-weight: 800; color: #10b981;"><?= $totalFleet ?>+</h2>
                <p style="color: #cbd5e1; font-size: 14px; font-weight: 600;">Vehicles In Fleet</p>
            </div>
            <div>
                <h2 style="font-size: 40px; font-weight: 800; color: #fbbf24;"><?= $totalCouriers + 85 ?>+</h2>
                <p style="color: #cbd5e1; font-size: 14px; font-weight: 600;">Packages Delivered</p>
            </div>
            <div>
                <h2 style="font-size: 40px; font-weight: 800; color: #a855f7;">99.8%</h2>
                <p style="color: #cbd5e1; font-size: 14px; font-weight: 600;">Customer Satisfaction</p>
            </div>
        </div>
    </div>
</section>

<!-- Customer Reviews Section -->
<?php if (!empty($recentReviews)): ?>
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Real Feedback</span>
            <h2 class="section-title">What Our Riders & Renters Say</h2>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
            <?php foreach ($recentReviews as $rev): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div class="star-rating">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="fa-solid fa-star" style="color: <?= $s <= $rev['rating'] ? '#f59e0b' : '#e2e8f0' ?>;"></i>
                            <?php endfor; ?>
                        </div>
                        <span style="font-size: 12px; color: var(--text-muted);"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                    </div>
                    <p style="font-style: italic; margin-bottom: 16px; color: var(--text-main);">"<?= htmlspecialchars($rev['review']) ?>"</p>
                    <div style="display: flex; align-items: center; gap: 10px; border-top: 1px solid var(--border); padding-top: 12px;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            <?= strtoupper(substr($rev['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <strong style="font-size: 14px; display: block;"><?= htmlspecialchars($rev['full_name']) ?></strong>
                            <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($rev['vehicle_name']) ?> (<?= $rev['category'] ?>)</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
