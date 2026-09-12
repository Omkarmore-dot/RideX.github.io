<?php
// vehicles.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Vehicle Fleet Catalog & Search";

$db = getDB();

// Handle Filters
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$transmission = isset($_GET['transmission']) ? trim($_GET['transmission']) : '';
$fuel = isset($_GET['fuel']) ? trim($_GET['fuel']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'id_asc';

// Build Dynamic SQL
$sql = "SELECT * FROM vehicles WHERE 1=1";
$params = [];
$types = "";

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($transmission)) {
    $sql .= " AND transmission = ?";
    $params[] = $transmission;
    $types .= "s";
}

if (!empty($fuel)) {
    $sql .= " AND fuel_type = ?";
    $params[] = $fuel;
    $types .= "s";
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR brand LIKE ? OR category LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Sorting
switch ($sort) {
    case 'price_km_asc':
        $sql .= " ORDER BY price_per_km ASC";
        break;
    case 'price_km_desc':
        $sql .= " ORDER BY price_per_km DESC";
        break;
    case 'price_day_asc':
        $sql .= " ORDER BY price_per_day ASC";
        break;
    case 'price_day_desc':
        $sql .= " ORDER BY price_per_day DESC";
        break;
    default:
        $sql .= " ORDER BY id ASC";
        break;
}

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categoriesList = getVehicleCategories();

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 30px; padding-bottom: 60px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; font-weight: 800; color: var(--dark);">Explore Our Fleet</h1>
            <p style="color: var(--text-muted); font-size: 15px;">Find the perfect ride, self-drive vehicle, or delivery hauler.</p>
        </div>
        <div>
            <span class="user-badge" style="background: #ffffff; border: 1px solid var(--border);">
                Showing <strong><?= count($vehicles) ?></strong> Vehicles
            </span>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card" style="margin-bottom: 30px; padding: 20px;">
        <form method="GET" action="vehicles.php">
            <div class="form-row" style="grid-template-columns: 2fr repeat(auto-fit, minmax(140px, 1fr)); align-items: end;">
                <!-- Search Keyword -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label><i class="fa-solid fa-magnifying-glass"></i> Search Fleet</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or brand (e.g. Honda, Yamaha)" value="<?= htmlspecialchars($search) ?>">
                </div>

                <!-- Category -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categoriesList as $catName => $v): ?>
                            <option value="<?= $catName ?>" <?= $category === $catName ? 'selected' : '' ?>><?= $catName ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Transmission -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Transmission</label>
                    <select name="transmission" class="form-control">
                        <option value="">Any</option>
                        <option value="Manual" <?= $transmission === 'Manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="Automatic" <?= $transmission === 'Automatic' ? 'selected' : '' ?>>Automatic</option>
                    </select>
                </div>

                <!-- Fuel Type -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Fuel</label>
                    <select name="fuel" class="form-control">
                        <option value="">Any</option>
                        <option value="Petrol" <?= $fuel === 'Petrol' ? 'selected' : '' ?>>Petrol</option>
                        <option value="Diesel" <?= $fuel === 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                        <option value="Electric" <?= $fuel === 'Electric' ? 'selected' : '' ?>>Electric</option>
                        <option value="CNG" <?= $fuel === 'CNG' ? 'selected' : '' ?>>CNG</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Sort By</label>
                    <select name="sort" class="form-control">
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>Default</option>
                        <option value="price_km_asc" <?= $sort === 'price_km_asc' ? 'selected' : '' ?>>Rate/KM: Low to High</option>
                        <option value="price_km_desc" <?= $sort === 'price_km_desc' ? 'selected' : '' ?>>Rate/KM: High to Low</option>
                        <option value="price_day_asc" <?= $sort === 'price_day_asc' ? 'selected' : '' ?>>Rent/Day: Low to High</option>
                        <option value="price_day_desc" <?= $sort === 'price_day_desc' ? 'selected' : '' ?>>Rent/Day: High to Low</option>
                    </select>
                </div>

                <!-- Submit / Reset Buttons -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="vehicles.php" class="btn btn-outline" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </div>
        </form>
    </div>

    <!-- Vehicles Grid -->
    <?php if (empty($vehicles)): ?>
        <div class="card" style="text-align: center; padding: 60px 20px;">
            <i class="fa-solid fa-car-tunnel" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;"></i>
            <h3>No Vehicles Match Your Search</h3>
            <p style="color: var(--text-muted); margin-bottom: 20px;">Try relaxing your filters or search keywords.</p>
            <a href="vehicles.php" class="btn btn-primary">Clear All Filters</a>
        </div>
    <?php else: ?>
        <div class="vehicle-grid">
            <?php foreach ($vehicles as $v): ?>
                <div class="vehicle-card">
                    <div class="vehicle-img-wrap">
                        <img src="<?= htmlspecialchars($v['image_url']) ?>" alt="<?= htmlspecialchars($v['name']) ?>" loading="lazy">
                        <span class="badge-cat"><?= htmlspecialchars($v['category']) ?></span>
                        <span class="badge-status badge-<?= strtolower($v['status']) ?>"><?= htmlspecialchars($v['status']) ?></span>
                    </div>
                    <div class="vehicle-body">
                        <h3><a href="vehicle_details.php?id=<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></a></h3>
                        <div class="vehicle-meta">
                            <span><i class="fa-solid fa-car"></i> <?= htmlspecialchars($v['brand']) ?></span>
                            <span><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($v['model_year']) ?></span>
                            <span><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($v['plate_number']) ?></span>
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
                                Ride Fare
                                <strong><?= formatCurrency($v['price_per_km']) ?>/km</strong>
                                <small style="color: var(--text-light);">Base <?= formatCurrency($v['base_fare']) ?></small>
                            </div>
                            <div class="price-item" style="text-align: right;">
                                Daily Rental
                                <strong><?= formatCurrency($v['price_per_day']) ?>/day</strong>
                            </div>
                        </div>

                        <div class="vehicle-actions">
                            <a href="booking.php?vehicle_id=<?= $v['id'] ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-taxi"></i> Book</a>
                            <a href="rental.php?vehicle_id=<?= $v['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-key"></i> Rent</a>
                        </div>
                        <div style="margin-top: 8px; text-align: center;">
                            <a href="vehicle_details.php?id=<?= $v['id'] ?>" style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                                <i class="fa-solid fa-circle-info"></i> View Full Specs & Reviews
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
