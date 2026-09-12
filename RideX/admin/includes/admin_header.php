<?php
// admin/includes/admin_header.php
require_once __DIR__ . '/../../config/db.php';

// --- Admin Access Control ---
// If a regular logged-in user (not admin) tries to access the admin area, show "No Access"
if (isLoggedIn() && !isAdminLoggedIn()) {
    $blockedUser = getCurrentUser();
    $blockedName = htmlspecialchars($blockedUser['full_name'] ?? 'User');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied | RideX Admin</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .no-access-card {
                background: #ffffff;
                border-radius: 16px;
                padding: 48px 40px;
                text-align: center;
                max-width: 460px;
                width: 100%;
                box-shadow: 0 25px 60px rgba(0,0,0,0.5);
                border-top: 5px solid #ef4444;
            }
            .icon-wrap {
                width: 80px; height: 80px; border-radius: 50%;
                background: #fee2e2; color: #dc2626;
                font-size: 36px;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 24px;
            }
            h1 { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
            p  { color: #64748b; font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
            .user-badge {
                display: inline-block;
                background: #f1f5f9; border: 1px solid #e2e8f0;
                border-radius: 8px; padding: 8px 16px;
                font-size: 13px; color: #475569; margin-bottom: 28px;
            }
            .btn-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
            .btn {
                display: inline-flex; align-items: center; gap: 8px;
                padding: 11px 22px; border-radius: 8px; font-weight: 600;
                font-size: 14px; text-decoration: none; cursor: pointer;
                border: none; transition: all 0.2s;
            }
            .btn-primary { background: #0284c7; color: #fff; }
            .btn-primary:hover { background: #0369a1; }
            .btn-outline { background: transparent; color: #475569; border: 1px solid #cbd5e1; }
            .btn-outline:hover { background: #f8fafc; }
        </style>
    </head>
    <body>
        <div class="no-access-card">
            <div class="icon-wrap">
                <i class="fa-solid fa-ban"></i>
            </div>
            <h1>No Access</h1>
            <p>The Admin Dashboard is restricted to authorized administrators only.</p>
            <div class="user-badge">
                <i class="fa-solid fa-circle-user"></i> Logged in as: <strong><?= $blockedName ?></strong>
            </div>
            <div class="btn-group">
                <a href="../index.php" class="btn btn-primary">
                    <i class="fa-solid fa-house"></i> Back to RideX
                </a>
                <a href="../logout.php" class="btn btn-outline">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// If nobody is logged in at all, redirect to admin login
if (!isAdminLoggedIn()) {
    $_SESSION['flash_error'] = "Admin access required. Please authenticate.";
    header("Location: login.php");
    exit();
}

$currentAdminPage = basename($_SERVER['PHP_SELF']);
$adminUsername = $_SESSION['admin_username'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($adminTitle) ? htmlspecialchars($adminTitle) . " | RideX Admin Console" : "RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System (Admin)" ?></title>
    
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core Style & Admin Style -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">

<!-- Sidebar Navigation -->
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="brand-icon" style="width: 36px; height: 36px; font-size: 16px;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h2>Ride<span>X</span> <small style="font-size: 11px; color: #94a3b8; font-weight: 500;">HQ</small></h2>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?= $currentAdminPage === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="vehicles.php" class="<?= $currentAdminPage === 'vehicles.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-car-side"></i>
                <span>Manage Vehicles</span>
            </a>
        </li>
        <li>
            <a href="bookings.php" class="<?= $currentAdminPage === 'bookings.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-taxi"></i>
                <span>Ride Bookings</span>
            </a>
        </li>
        <li>
            <a href="rentals.php" class="<?= $currentAdminPage === 'rentals.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-key"></i>
                <span>Vehicle Rentals</span>
            </a>
        </li>
        <li>
            <a href="courier.php" class="<?= $currentAdminPage === 'courier.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-packing"></i>
                <span>Courier Deliveries</span>
            </a>
        </li>
        <li>
            <a href="users.php" class="<?= $currentAdminPage === 'users.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Customer Accounts</span>
            </a>
        </li>
        <li>
            <a href="reviews.php" class="<?= $currentAdminPage === 'reviews.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-star-half-stroke"></i>
                <span>Ratings & Reviews</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="../index.php" target="_blank" style="color: #38bdf8; display: block; margin-bottom: 8px;">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Website
        </a>
        <a href="logout.php" style="color: #f87171; display: block;">
            <i class="fa-solid fa-right-from-bracket"></i> Sign Out Admin
        </a>
    </div>
</aside>

<!-- Main Admin Layout Area -->
<div class="admin-main">
    <!-- Top Administrative Header -->
    <header class="admin-topbar">
        <div>
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--admin-text);">
                <?= isset($adminTitle) ? htmlspecialchars($adminTitle) : 'Administration Console' ?>
            </h3>
        </div>

        <div style="display: flex; align-items: center; gap: 20px;">
            <span style="font-size: 13px; color: var(--admin-muted);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y') ?></span>
            
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--admin-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <strong style="font-size: 13px; display: block; line-height: 1.2;"><?= htmlspecialchars($adminUsername) ?></strong>
                    <span style="font-size: 11px; color: #16a34a; font-weight: 600;">Super Administrator</span>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-container">
        <!-- Global Flash Alerts for Admin -->
        <?= getFlash() ?>
