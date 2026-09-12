<?php
// ========================================================
// RideX - Database Configuration & Core Helper Functions
// ========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Parameters (Standard XAMPP Defaults)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ridex_db');
define('DB_PORT', 3306);

// Global connection instance
$db = null;

try {
    // Attempt connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($db->connect_error) {
        throw new Exception($db->connect_error);
    }
    
    $db->set_charset("utf8mb4");
} catch (Exception $e) {
    // Check if error is due to database not existing yet
    $err = $e->getMessage();
    $dbNotSelected = (strpos($err, 'Unknown database') !== false || $db === null || (isset($db->connect_errno) && $db->connect_errno === 1049));
    
    // We provide a styled fallback setup helper if database is missing
    if ($dbNotSelected) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>RideX - Database Setup Required</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; line-height: 1.6; }
                .setup-card { max-width: 650px; margin: 40px auto; background: #1e293b; border-radius: 12px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border: 1px solid #334155; }
                h1 { color: #38bdf8; margin-top: 0; font-size: 26px; }
                .badge { display: inline-block; background: #ef4444; color: white; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 13px; margin-bottom: 15px; }
                code { background: #0f172a; color: #38bdf8; padding: 2px 6px; border-radius: 4px; font-family: Consolas, monospace; }
                ol { padding-left: 20px; }
                li { margin-bottom: 12px; }
                .btn { display: inline-block; background: #0284c7; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; margin-top: 15px; }
                .btn:hover { background: #0369a1; }
            </style>
        </head>
        <body>
            <div class="setup-card">
                <span class="badge">DATABASE NOT DETECTED</span>
                <h1>RideX Database Setup Required</h1>
                <p>MySQL is running, but the database <code>ridex_db</code> has not been imported yet.</p>
                <ol>
                    <li>Open <strong>phpMyAdmin</strong> at <a href="http://localhost/phpmyadmin" target="_blank" style="color: #38bdf8;">http://localhost/phpmyadmin</a></li>
                    <li>Click on <strong>New</strong> on the left panel to create a database.</li>
                    <li>Name it <code>ridex_db</code> with Collation <code>utf8mb4_unicode_ci</code> and click <strong>Create</strong>.</li>
                    <li>Click on the <strong>Import</strong> tab at the top.</li>
                    <li>Browse and select the SQL file: <br><code>RideX/database/ridex_db.sql</code></li>
                    <li>Scroll down and click <strong>Import / Go</strong>.</li>
                </ol>
                <a href="" class="btn" onclick="location.reload(); return false;">I Have Imported - Refresh RideX</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    } else {
        // Generic database connection error (e.g. MySQL service stopped)
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>RideX - Database Connection Notice</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; }
                .setup-card { max-width: 600px; margin: 40px auto; background: #1e293b; border-radius: 12px; padding: 30px; border: 1px solid #ef4444; }
                h2 { color: #f87171; margin-top: 0; }
                code { background: #0f172a; color: #fbbf24; padding: 3px 6px; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="setup-card">
                <h2>Unable to Connect to MySQL</h2>
                <p>Please make sure <strong>MySQL</strong> is started in your <strong>XAMPP Control Panel</strong>.</p>
                <p>Error details: <code><?= htmlspecialchars($err) ?></code></p>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

/**
 * Returns the MySQLi database connection instance
 */
function getDB() {
    global $db;
    return $db;
}

/**
 * Sanitize string for output
 */
function sanitize($input) {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if a regular user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforce regular user authentication
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Please log in to continue.";
        $currentUrl = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header("Location: login.php?redirect=" . $currentUrl);
        exit();
    }
}

/**
 * Check if an admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Enforce admin-area access before any page-specific POST/DB action.
 * Regular users receive the existing "No Access" experience; guests go to admin login.
 */
function enforceAdminAreaAccess() {
    if (isLoggedIn() && !isAdminLoggedIn()) {
        $blockedUser = getCurrentUser();
        $blockedName = htmlspecialchars($blockedUser['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>No Access | RideX Admin</title>
            <link rel="stylesheet" href="../css/style.css">
            <style>
                body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a,#1e1b4b);font-family:Inter,Arial,sans-serif;padding:20px}
                .no-access-card{background:#fff;border-radius:16px;padding:48px 40px;text-align:center;max-width:460px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.5);border-top:5px solid #ef4444}
                .icon-wrap{width:80px;height:80px;border-radius:50%;background:#fee2e2;color:#dc2626;font-size:36px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px}
                .no-access-card h1{font-size:28px;font-weight:800;color:#0f172a;margin-bottom:8px}
                .no-access-card p{color:#64748b;font-size:15px;line-height:1.6;margin-bottom:28px}
                .btn{display:inline-block;padding:11px 22px;border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;background:#0284c7;color:#fff}
            </style>
        </head>
        <body>
            <div class="no-access-card">
                <div class="icon-wrap">!</div>
                <h1>No Access</h1>
                <p>The Admin Panel is restricted to authorized administrators only.</p>
                <p>Logged in as: <strong><?= $blockedName ?></strong></p>
                <a class="btn" href="../index.php">Back to RideX</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    if (!isAdminLoggedIn()) {
        $_SESSION['flash_error'] = "Admin access required. Please authenticate.";
        header("Location: login.php");
        exit();
    }
}

/**
 * Enforce admin authentication
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        $_SESSION['flash_error'] = "Admin access required. Please authenticate.";
        header("Location: login.php");
        exit();
    }
}

/**
 * Get current logged in user record
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $userId = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id, full_name, email, phone, address, avatar, status, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc();
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}

/**
 * Set flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Display and clear flash message
 */
function getFlash() {
    $types = ['success', 'error', 'info', 'warning'];
    $output = '';
    foreach ($types as $t) {
        $key = 'flash_' . $t;
        if (isset($_SESSION[$key])) {
            $msg = htmlspecialchars($_SESSION[$key]);
            $output .= "<div class='alert alert-{$t}'>{$msg}</div>";
            unset($_SESSION[$key]);
        }
    }
    return $output;
}

/**
 * Create a user notification
 */
function createNotification($userId, $title, $message, $link = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $link);
    return $stmt->execute();
}

/**
 * Append courier tracking milestone
 */
function addCourierTrackingEvent($courierId, $status, $location, $remarks = '') {
    $db = getDB();
    
    // Update main courier status
    $stmtUpdate = $db->prepare("UPDATE courier_bookings SET current_status = ? WHERE id = ?");
    $stmtUpdate->bind_param("si", $status, $courierId);
    $stmtUpdate->execute();
    
    // Insert history tracking milestone
    $stmtTrack = $db->prepare("INSERT INTO courier_tracking (courier_id, status, location, remarks) VALUES (?, ?, ?, ?)");
    $stmtTrack->bind_param("isss", $courierId, $status, $location, $remarks);
    return $stmtTrack->execute();
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($userId) {
    if (!$userId) return 0;
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return (int)($res['unread_count'] ?? 0);
}

/**
 * Get categories list
 */
function getVehicleCategories() {
    return [
        'Bike' => ['icon' => 'fa-motorcycle', 'desc' => 'Fast, nimble 2-wheelers for solo city commutes'],
        'Scooter' => ['icon' => 'fa-moped', 'desc' => 'Economic gearless scooters for effortless hops'],
        'Auto Rickshaw' => ['icon' => 'fa-taxi', 'desc' => 'Traditional convenient 3-wheelers for short rides'],
        'Car' => ['icon' => 'fa-car-side', 'desc' => 'Comfortable sedans & hatchbacks for daily travel'],
        'SUV' => ['icon' => 'fa-truck-monster', 'desc' => 'Spacious high-ground clearance family all-rounders'],
        'Van' => ['icon' => 'fa-van-shuttle', 'desc' => 'Large capacity multi-seaters for group trips'],
        'Commercial Vehicle' => ['icon' => 'fa-truck-front', 'desc' => 'Heavy duty haulers & pickups for bulky cargo']
    ];
}
?>
