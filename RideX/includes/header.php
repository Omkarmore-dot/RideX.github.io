<?php
// includes/header.php
require_once __DIR__ . '/../config/db.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$currentUser = getCurrentUser();
$unreadNotifs = $currentUser ? getUnreadNotificationCount($currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . " | RideX" : "RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System" ?></title>
    
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Main Navigation Bar -->
<header class="navbar">
    <div class="container nav-container">
        <!-- Logo -->
        <a href="index.php" class="brand-logo" title="RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System">
            <div class="brand-icon">
                <i class="fa-solid fa-bolt-lightning"></i>
            </div>
            Ride<span>X</span>
        </a>

        <!-- Main Navigation Links -->
        <ul class="nav-links">
            <li><a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
            <li><a href="vehicles.php" class="<?= $currentPage === 'vehicles.php' || $currentPage === 'vehicle_details.php' ? 'active' : '' ?>">Vehicles</a></li>
            <li><a href="booking.php" class="<?= $currentPage === 'booking.php' ? 'active' : '' ?>">Book Ride</a></li>
            <li><a href="rental.php" class="<?= $currentPage === 'rental.php' ? 'active' : '' ?>">Rent Vehicle</a></li>
            <li><a href="courier.php" class="<?= $currentPage === 'courier.php' ? 'active' : '' ?>">Courier Delivery</a></li>
            <li><a href="tracking.php" class="<?= $currentPage === 'tracking.php' ? 'active' : '' ?>">Track Courier</a></li>
        </ul>

        <!-- Right User Actions -->
        <div class="nav-actions">
            <?php if ($currentUser): ?>
                <!-- Notifications Bell -->
                <a href="dashboard.php#notifications" class="notif-bell" title="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unreadNotifs > 0): ?>
                        <span class="badge"><?= $unreadNotifs ?></span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown Profile -->
                <div class="nav-user">
                    <a href="dashboard.php" class="user-badge">
                        <i class="fa-solid fa-circle-user"></i>
                        <span><?= htmlspecialchars(explode(' ', $currentUser['full_name'])[0]) ?></span>
                    </a>
                    <a href="profile.php" class="btn btn-sm btn-outline" title="My Profile"><i class="fa-solid fa-gear"></i></a>
                    <a href="logout.php" class="btn btn-sm btn-danger" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-outline">Sign In</a>
                <a href="register.php" class="btn btn-sm btn-primary">Get Started</a>
            <?php endif; ?>


            <!-- Mobile Hamburger Button -->
            <button class="menu-toggle" aria-label="Toggle Navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<!-- Global Flash Messages Notification Banner -->
<div class="container" style="margin-top: 15px;">
    <?= getFlash() ?>
</div>
