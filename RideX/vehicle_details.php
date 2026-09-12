<?php
// vehicle_details.php
require_once __DIR__ . '/config/db.php';

$vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

$stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->bind_param("i", $vehicleId);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    setFlash('error', 'The requested vehicle was not found.');
    header("Location: vehicles.php");
    exit();
}

$pageTitle = $vehicle['name'] . " (" . $vehicle['category'] . ")";

// Fetch reviews for this vehicle
$stmtRev = $db->prepare("SELECT r.*, u.full_name, u.created_at as member_since 
                         FROM ratings r 
                         JOIN users u ON r.user_id = u.id 
                         WHERE r.vehicle_id = ? 
                         ORDER BY r.created_at DESC");
$stmtRev->bind_param("i", $vehicleId);
$stmtRev->execute();
$reviews = $stmtRev->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate average rating
$avgRating = 0;
if (count($reviews) > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / count($reviews), 1);
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 20px; font-size: 14px; color: var(--text-muted);">
        <a href="index.php">Home</a> &nbsp;/&nbsp; 
        <a href="vehicles.php">Vehicles</a> &nbsp;/&nbsp; 
        <a href="vehicles.php?category=<?= urlencode($vehicle['category']) ?>"><?= htmlspecialchars($vehicle['category']) ?></a> &nbsp;/&nbsp; 
        <span style="color: var(--dark); font-weight: 600;"><?= htmlspecialchars($vehicle['name']) ?></span>
    </div>

    <!-- Vehicle Main Overview -->
    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 35px; align-items: start; margin-bottom: 50px;">
        <!-- Left: Image Gallery & Specs -->
        <div>
            <div style="border-radius: var(--radius-lg); overflow: hidden; height: 380px; box-shadow: var(--shadow-md); margin-bottom: 24px; position: relative;">
                <img src="<?= htmlspecialchars($vehicle['image_url']) ?>" alt="<?= htmlspecialchars($vehicle['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <span class="badge-cat" style="font-size: 13px; padding: 6px 14px;"><?= htmlspecialchars($vehicle['category']) ?></span>
                <span class="badge-status badge-<?= strtolower($vehicle['status']) ?>" style="font-size: 13px; padding: 6px 14px;"><?= htmlspecialchars($vehicle['status']) ?></span>
            </div>

            <!-- Detailed Specifications Grid -->
            <div class="card">
                <h3 class="card-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Technical Specifications</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-top: 15px;">
                    <div style="background: #f8fafc; padding: 12px; border-radius: var(--radius-md);">
                        <span style="font-size: 12px; color: var(--text-muted);">Manufacturer</span>
                        <h4 style="font-size: 15px;"><?= htmlspecialchars($vehicle['brand']) ?></h4>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: var(--radius-md);">
                        <span style="font-size: 12px; color: var(--text-muted);">Model Year</span>
                        <h4 style="font-size: 15px;"><?= htmlspecialchars($vehicle['model_year']) ?></h4>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: var(--radius-md);">
                        <span style="font-size: 12px; color: var(--text-muted);">Registration Plate</span>
                        <h4 style="font-size: 15px;"><?= htmlspecialchars($vehicle['plate_number']) ?></h4>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: var(--radius-md);">
                        <span style="font-size: 12px; color: var(--text-muted);">Seating Capacity</span>
                        <h4 style="font-size: 15px;"><?= $vehicle['seating_capacity'] ?> Passenger(s)</h4>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: var(--radius-md);">
                        <span style="font-size: 12px; color: var(--text-muted);">Fuel Type</span>
                        <h4 style="font-size: 15px;"><?= htmlspecialchars($vehicle['fuel_type']) ?></h4>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: var(--radius-md);">
                        <span style="font-size: 12px; color: var(--text-muted);">Transmission</span>
                        <h4 style="font-size: 15px;"><?= htmlspecialchars($vehicle['transmission']) ?></h4>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border);">
                    <h4 style="font-size: 14px; margin-bottom: 6px;">Vehicle Description</h4>
                    <p style="color: var(--text-muted); font-size: 14px;"><?= nl2br(htmlspecialchars($vehicle['description'] ?? 'No description provided.')) ?></p>
                </div>
            </div>
        </div>

        <!-- Right: Action & Pricing Card -->
        <div>
            <div class="card" style="box-shadow: var(--shadow-lg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h1 style="font-size: 26px; font-weight: 800; color: var(--dark);"><?= htmlspecialchars($vehicle['name']) ?></h1>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 5px;">
                            <div class="star-rating">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="fa-solid fa-star" style="color: <?= $s <= round($avgRating) ? '#f59e0b' : '#cbd5e1' ?>; font-size: 14px;"></i>
                                <?php endfor; ?>
                            </div>
                            <span style="font-weight: 700; font-size: 14px;"><?= $avgRating > 0 ? $avgRating . ' / 5.0' : 'Unrated' ?></span>
                            <span style="font-size: 13px; color: var(--text-muted);">(<?= count($reviews) ?> reviews)</span>
                        </div>
                    </div>
                </div>

                <!-- Price Box -->
                <div class="fare-breakdown" style="margin: 20px 0;">
                    <div class="fare-row">
                        <span>Ride Base Fare:</span>
                        <strong style="color: var(--dark);"><?= formatCurrency($vehicle['base_fare']) ?></strong>
                    </div>
                    <div class="fare-row">
                        <span>Ride Distance Rate:</span>
                        <strong style="color: var(--dark);"><?= formatCurrency($vehicle['price_per_km']) ?> / km</strong>
                    </div>
                    <div class="fare-row total">
                        <span>Daily Rental Rate:</span>
                        <span><?= formatCurrency($vehicle['price_per_day']) ?> / day</span>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 25px;">
                    <a href="booking.php?vehicle_id=<?= $vehicle['id'] ?>" class="btn btn-primary btn-lg btn-block">
                        <i class="fa-solid fa-taxi"></i> Book Ride with this <?= htmlspecialchars($vehicle['category']) ?>
                    </a>
                    <a href="rental.php?vehicle_id=<?= $vehicle['id'] ?>" class="btn btn-secondary btn-lg btn-block">
                        <i class="fa-solid fa-key"></i> Rent for Self-Drive
                    </a>
                </div>

                <div style="margin-top: 20px; font-size: 12px; color: var(--text-muted); text-align: center;">
                    <i class="fa-solid fa-shield-check" style="color: var(--accent);"></i> Insured, regularly sanitized, and verified by RideX.
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Reviews & Rating Breakdown -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
            <h2 style="font-size: 20px; font-weight: 700;"><i class="fa-solid fa-comments" style="color: var(--primary);"></i> Customer Reviews (<?= count($reviews) ?>)</h2>
            <span style="font-size: 13px; color: var(--text-muted);">Verified bookings from registered riders</span>
        </div>

        <?php if (empty($reviews)): ?>
            <p style="color: var(--text-muted); font-style: italic;">No reviews have been written for this vehicle yet. Book a ride and be the first to share your experience!</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($reviews as $rev): ?>
                    <div style="padding-bottom: 16px; border-bottom: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                                    <?= strtoupper(substr($rev['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong style="font-size: 14px;"><?= htmlspecialchars($rev['full_name']) ?></strong>
                                    <span class="status-pill status-confirmed" style="font-size: 10px; margin-left: 6px;">Verified Trip</span>
                                </div>
                            </div>
                            <span style="font-size: 12px; color: var(--text-muted);"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                        </div>
                        <div class="star-rating" style="margin-bottom: 6px;">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="fa-solid fa-star" style="color: <?= $s <= $rev['rating'] ? '#f59e0b' : '#cbd5e1' ?>; font-size: 12px;"></i>
                            <?php endfor; ?>
                        </div>
                        <p style="font-size: 14px; color: var(--text-main); margin-bottom: 0;"><?= htmlspecialchars($rev['review']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
