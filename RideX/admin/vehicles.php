<?php
// admin/vehicles.php
require_once __DIR__ . '/../config/db.php';
enforceAdminAreaAccess();
$adminTitle = "Vehicle Fleet Management";

$db = getDB();
$categoriesList = getVehicleCategories();

$error = '';
$action = $_GET['action'] ?? 'list';
$editVehicle = null;

// Handle Delete
if ($action === 'delete') {
    $vehicleId = (int)($_GET['id'] ?? 0);
    if ($vehicleId) {
        $stmt = $db->prepare("DELETE FROM vehicles WHERE id = ?");
        $stmt->bind_param("i", $vehicleId);
        if ($stmt->execute()) {
            setFlash('success', "Vehicle #{$vehicleId} deleted successfully.");
        } else {
            setFlash('error', "Failed to delete vehicle: " . $db->error);
        }
    }
    header("Location: vehicles.php");
    exit();
}

// Handle Quick Status Toggle
if ($action === 'toggle_status') {
    $vehicleId = (int)($_GET['id'] ?? 0);
    $newStatus = trim($_GET['status'] ?? 'available');
    $valid = ['available', 'booked', 'rented', 'maintenance'];
    if ($vehicleId && in_array($newStatus, $valid)) {
        $stmt = $db->prepare("UPDATE vehicles SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $vehicleId);
        $stmt->execute();
        setFlash('success', "Vehicle #{$vehicleId} availability status changed to '{$newStatus}'.");
    }
    header("Location: vehicles.php");
    exit();
}

// Handle Add / Edit Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? 'add';
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'Car');
    $brand = trim($_POST['brand'] ?? '');
    $modelYear = (int)($_POST['model_year'] ?? date('Y'));
    $plateNumber = strtoupper(trim($_POST['plate_number'] ?? ''));
    $seatingCapacity = (int)($_POST['seating_capacity'] ?? 4);
    $fuelType = trim($_POST['fuel_type'] ?? 'Petrol');
    $transmission = trim($_POST['transmission'] ?? 'Manual');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $baseFare = (float)($_POST['base_fare'] ?? 30.00);
    $pricePerKm = (float)($_POST['price_per_km'] ?? 10.00);
    $pricePerDay = (float)($_POST['price_per_day'] ?? 500.00);
    $status = trim($_POST['status'] ?? 'available');
    $description = trim($_POST['description'] ?? '');

    // Fallback stock image if blank
    if (empty($imageUrl)) {
        $imageUrl = 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=800&q=80';
    }

    if (empty($name) || empty($brand) || empty($plateNumber)) {
        $error = "Please fill in vehicle name, brand, and plate number.";
    } else {
        if ($postAction === 'add') {
            // Check plate unique
            $chk = $db->prepare("SELECT id FROM vehicles WHERE plate_number = ?");
            $chk->bind_param("s", $plateNumber);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = "A vehicle with license plate {$plateNumber} already exists.";
            } else {
                $stmt = $db->prepare("INSERT INTO vehicles (name, category, brand, model_year, plate_number, seating_capacity, fuel_type, transmission, image_url, base_fare, price_per_km, price_per_day, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisissssdddss", $name, $category, $brand, $modelYear, $plateNumber, $seatingCapacity, $fuelType, $transmission, $imageUrl, $baseFare, $pricePerKm, $pricePerDay, $status, $description);
                if ($stmt->execute()) {
                    setFlash('success', "Vehicle '{$name}' added to fleet successfully.");
                    header("Location: vehicles.php");
                    exit();
                } else {
                    $error = "Error adding vehicle: " . $db->error;
                }
            }
        } elseif ($postAction === 'edit') {
            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
            $stmt = $db->prepare("UPDATE vehicles SET name = ?, category = ?, brand = ?, model_year = ?, plate_number = ?, seating_capacity = ?, fuel_type = ?, transmission = ?, image_url = ?, base_fare = ?, price_per_km = ?, price_per_day = ?, status = ?, description = ? WHERE id = ?");
            $stmt->bind_param("sssisissssdddssi", $name, $category, $brand, $modelYear, $plateNumber, $seatingCapacity, $fuelType, $transmission, $imageUrl, $baseFare, $pricePerKm, $pricePerDay, $status, $description, $vehicleId);
            if ($stmt->execute()) {
                setFlash('success', "Vehicle #{$vehicleId} updated successfully.");
                header("Location: vehicles.php");
                exit();
            } else {
                $error = "Error updating vehicle: " . $db->error;
            }
        }
    }
}

// If edit mode, load vehicle
if ($action === 'edit') {
    $vehicleId = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $editVehicle = $stmt->get_result()->fetch_assoc();
    if (!$editVehicle) {
        setFlash('error', 'Vehicle not found.');
        header("Location: vehicles.php");
        exit();
    }
}

// Fetch all vehicles
$catFilter = trim($_GET['cat_filter'] ?? '');
$sql = "SELECT * FROM vehicles WHERE 1=1";
if (!empty($catFilter)) {
    $sql .= " AND category = " . $db->real_escape_string("'$catFilter'");
}
$sql .= " ORDER BY id DESC";
$vehiclesList = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--admin-text); margin: 0;">Vehicle Fleet Management</h1>
        <p style="color: var(--admin-muted); font-size: 14px; margin: 4px 0 0;">Add, modify, monitor, and configure all 7 vehicle categories.</p>
    </div>
    <div>
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <a href="vehicles.php" class="btn btn-outline"><i class="fa-solid fa-list"></i> Back to Fleet List</a>
        <?php else: ?>
            <a href="vehicles.php?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add New Vehicle</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Add / Edit Vehicle Form Card -->
    <div class="admin-card" style="max-width: 900px;">
        <div class="admin-card-header">
            <h3><i class="fa-solid fa-car" style="color: var(--admin-primary);"></i> <?= $action === 'edit' ? 'Edit Vehicle: ' . htmlspecialchars($editVehicle['name']) : 'Add New Fleet Vehicle' ?></h3>
        </div>

        <form action="vehicles.php" method="POST">
            <input type="hidden" name="post_action" value="<?= $action ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="vehicle_id" value="<?= $editVehicle['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Vehicle Name / Model *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Honda Civic Turbo" value="<?= htmlspecialchars($editVehicle['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" class="form-control" required>
                        <?php foreach ($categoriesList as $cat => $inf): ?>
                            <option value="<?= $cat ?>" <?= (isset($editVehicle) && $editVehicle['category'] === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Brand / Manufacturer *</label>
                    <input type="text" name="brand" class="form-control" placeholder="e.g. Honda" value="<?= htmlspecialchars($editVehicle['brand'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Model Year *</label>
                    <input type="number" name="model_year" class="form-control" placeholder="2023" value="<?= htmlspecialchars($editVehicle['model_year'] ?? date('Y')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Registration Plate Number *</label>
                    <input type="text" name="plate_number" class="form-control" placeholder="e.g. RX-CR-401" value="<?= htmlspecialchars($editVehicle['plate_number'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Seating Capacity</label>
                    <input type="number" name="seating_capacity" class="form-control" value="<?= htmlspecialchars($editVehicle['seating_capacity'] ?? 4) ?>" min="1" max="50">
                </div>
                <div class="form-group">
                    <label>Fuel Type</label>
                    <select name="fuel_type" class="form-control">
                        <option value="Petrol" <?= (isset($editVehicle) && $editVehicle['fuel_type'] === 'Petrol') ? 'selected' : '' ?>>Petrol</option>
                        <option value="Diesel" <?= (isset($editVehicle) && $editVehicle['fuel_type'] === 'Diesel') ? 'selected' : '' ?>>Diesel</option>
                        <option value="Electric" <?= (isset($editVehicle) && $editVehicle['fuel_type'] === 'Electric') ? 'selected' : '' ?>>Electric</option>
                        <option value="CNG" <?= (isset($editVehicle) && $editVehicle['fuel_type'] === 'CNG') ? 'selected' : '' ?>>CNG</option>
                        <option value="Hybrid" <?= (isset($editVehicle) && $editVehicle['fuel_type'] === 'Hybrid') ? 'selected' : '' ?>>Hybrid</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transmission</label>
                    <select name="transmission" class="form-control">
                        <option value="Manual" <?= (isset($editVehicle) && $editVehicle['transmission'] === 'Manual') ? 'selected' : '' ?>>Manual</option>
                        <option value="Automatic" <?= (isset($editVehicle) && $editVehicle['transmission'] === 'Automatic') ? 'selected' : '' ?>>Automatic</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Base Fare ($) *</label>
                    <input type="number" name="base_fare" class="form-control" step="0.50" value="<?= htmlspecialchars($editVehicle['base_fare'] ?? '30.00') ?>" required>
                </div>
                <div class="form-group">
                    <label>Price per KM ($) *</label>
                    <input type="number" name="price_per_km" class="form-control" step="0.25" value="<?= htmlspecialchars($editVehicle['price_per_km'] ?? '10.00') ?>" required>
                </div>
                <div class="form-group">
                    <label>Rental Price per Day ($) *</label>
                    <input type="number" name="price_per_day" class="form-control" step="1.00" value="<?= htmlspecialchars($editVehicle['price_per_day'] ?? '500.00') ?>" required>
                </div>
                <div class="form-group">
                    <label>Availability Status</label>
                    <select name="status" class="form-control">
                        <option value="available" <?= (isset($editVehicle) && $editVehicle['status'] === 'available') ? 'selected' : '' ?>>Available</option>
                        <option value="booked" <?= (isset($editVehicle) && $editVehicle['status'] === 'booked') ? 'selected' : '' ?>>Booked</option>
                        <option value="rented" <?= (isset($editVehicle) && $editVehicle['status'] === 'rented') ? 'selected' : '' ?>>Rented</option>
                        <option value="maintenance" <?= (isset($editVehicle) && $editVehicle['status'] === 'maintenance') ? 'selected' : '' ?>>Maintenance</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Vehicle Image URL</label>
                <input type="url" name="image_url" class="form-control" placeholder="https://images.unsplash.com/..." value="<?= htmlspecialchars($editVehicle['image_url'] ?? '') ?>">
                <small style="color: var(--admin-muted); font-size: 11px;">Enter a direct image link. Unsplash vehicle images work great.</small>
            </div>

            <div class="form-group">
                <label>Vehicle Description</label>
                <textarea name="description" class="form-control" placeholder="Features, engine size, cargo capacity, or special conditions"><?= htmlspecialchars($editVehicle['description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $action === 'edit' ? 'Save Changes' : 'Create Vehicle' ?></button>
                <a href="vehicles.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <!-- Vehicles List View -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fa-solid fa-car-rear" style="color: var(--admin-primary);"></i> Fleet Vehicles (<?= count($vehiclesList) ?>)</h3>
            
            <!-- Category Filter Bar -->
            <form method="GET" action="vehicles.php" style="display: flex; gap: 8px;">
                <select name="cat_filter" class="form-select-sm" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categoriesList as $cat => $inf): ?>
                        <option value="<?= $cat ?>" <?= $catFilter === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($catFilter)): ?>
                    <a href="vehicles.php" class="btn btn-sm btn-outline" title="Clear Filter"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name & Brand</th>
                        <th>Category</th>
                        <th>Plate #</th>
                        <th>Specs</th>
                        <th>Rates (Ride / Rental)</th>
                        <th>Status</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehiclesList)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--admin-muted);">No vehicles found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vehiclesList as $v): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($v['image_url']) ?>" alt="Vehicle" style="width: 60px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid var(--admin-border);">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($v['name']) ?></strong><br>
                                    <small style="color: var(--admin-muted);"><?= htmlspecialchars($v['brand']) ?> &bull; <?= $v['model_year'] ?></small>
                                </td>
                                <td><span class="status-pill status-confirmed"><?= htmlspecialchars($v['category']) ?></span></td>
                                <td><strong style="font-family: monospace;"><?= htmlspecialchars($v['plate_number']) ?></strong></td>
                                <td>
                                    <small>
                                        <?= $v['seating_capacity'] ?> seats &bull; <?= $v['fuel_type'] ?><br>
                                        <?= $v['transmission'] ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?= formatCurrency($v['price_per_km']) ?>/km</strong><br>
                                    <small style="color: var(--admin-primary); font-weight: 600;"><?= formatCurrency($v['price_per_day']) ?>/day</small>
                                </td>
                                <td>
                                    <span class="badge-status badge-<?= strtolower($v['status']) ?>" style="position: static; display: inline-block;">
                                        <?= htmlspecialchars($v['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <a href="vehicles.php?action=edit&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline" title="Edit Vehicle"><i class="fa-solid fa-pen"></i></a>
                                        
                                        <!-- Quick Toggle Availability Dropdown -->
                                        <select class="form-select-sm" onchange="location.href='vehicles.php?action=toggle_status&id=<?= $v['id'] ?>&status=' + this.value;">
                                            <option value="available" <?= $v['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                            <option value="booked" <?= $v['status'] === 'booked' ? 'selected' : '' ?>>Booked</option>
                                            <option value="rented" <?= $v['status'] === 'rented' ? 'selected' : '' ?>>Rented</option>
                                            <option value="maintenance" <?= $v['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                        </select>

                                        <a href="vehicles.php?action=delete&id=<?= $v['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this vehicle?');" title="Delete Vehicle"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
