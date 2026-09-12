<?php
// migrate.php — RideX One-Time Database Migration Script
// Run this ONCE via browser: http://localhost/RideX/migrate.php
// This file auto-deletes itself after successful execution.

require_once __DIR__ . '/config/db.php';
$db = getDB();

$results = [];
$allOk   = true;

// ----------------------------------------------------------------
// 0. Add payment-pending states used before Razorpay verification.
// ----------------------------------------------------------------
$bookingStatus = $db->query("SHOW COLUMNS FROM bookings LIKE 'status'")->fetch_assoc();
if ($bookingStatus && strpos($bookingStatus['Type'], "'Payment Pending'") === false) {
    $r = $db->query("ALTER TABLE bookings MODIFY status ENUM('Payment Pending','Confirmed','Driver Assigned','On The Way','Completed','Cancelled') NOT NULL DEFAULT 'Payment Pending'");
    if (!$r) {
        $results[] = ['status' => 'err', 'msg' => "Failed to add bookings Payment Pending status: " . $db->error];
        $allOk = false;
    } else {
        $results[] = ['status' => 'ok', 'msg' => "bookings Payment Pending status added."];
    }
} else {
    $results[] = ['status' => 'ok', 'msg' => "bookings Payment Pending status already exists — skipped."];
}

$courierStatus = $db->query("SHOW COLUMNS FROM courier_bookings LIKE 'current_status'")->fetch_assoc();
if ($courierStatus && strpos($courierStatus['Type'], "'Payment Pending'") === false) {
    $r = $db->query("ALTER TABLE courier_bookings MODIFY current_status ENUM('Payment Pending','Booking Confirmed','Pickup Assigned','Picked Up','In Transit','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Payment Pending'");
    if (!$r) {
        $results[] = ['status' => 'err', 'msg' => "Failed to add courier Payment Pending status: " . $db->error];
        $allOk = false;
    } else {
        $results[] = ['status' => 'ok', 'msg' => "courier Payment Pending status added."];
    }
} else {
    $results[] = ['status' => 'ok', 'msg' => "courier Payment Pending status already exists — skipped."];
}

// ----------------------------------------------------------------
// 0b. Add Razorpay as a payment method for verified online payments.
// ----------------------------------------------------------------
$paymentMethodCol = $db->query("SHOW COLUMNS FROM bookings LIKE 'payment_method'")->fetch_assoc();
if ($paymentMethodCol && strpos($paymentMethodCol['Type'], "'Razorpay'") === false) {
    $r = $db->query("ALTER TABLE bookings MODIFY payment_method ENUM('Cash','Card','UPI','Wallet','Razorpay') NOT NULL DEFAULT 'Cash'");
    if (!$r) {
        $results[] = ['status' => 'err', 'msg' => "Failed to add Razorpay payment method: " . $db->error];
        $allOk = false;
    } else {
        $results[] = ['status' => 'ok', 'msg' => "Razorpay payment method added to bookings."];
    }
} else {
    $results[] = ['status' => 'ok', 'msg' => "Razorpay payment method already exists — skipped."];
}

// ----------------------------------------------------------------
// 1. Add payment_status column to courier_bookings (if not exists)
// ----------------------------------------------------------------
$colCheck = $db->query("SHOW COLUMNS FROM courier_bookings LIKE 'payment_status'");
if ($colCheck->num_rows === 0) {
    $r = $db->query("ALTER TABLE courier_bookings ADD COLUMN payment_status ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending' AFTER delivery_fee");
    if ($r) {
        $results[] = ['status' => 'ok', 'msg' => "courier_bookings.payment_status column ADDED successfully."];
    } else {
        $results[] = ['status' => 'err', 'msg' => "Failed to add payment_status: " . $db->error];
        $allOk = false;
    }
} else {
    $results[] = ['status' => 'ok', 'msg' => "courier_bookings.payment_status column already exists — skipped."];
}

// ----------------------------------------------------------------
// 2. Fix admin password — set correct bcrypt hash for 'admin123'
// ----------------------------------------------------------------
$correctHash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $correctHash);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $results[] = ['status' => 'ok', 'msg' => "Admin password updated to a correct bcrypt hash of 'admin123'."];
    } else {
        // May already be correct, or admin row may not exist — verify
        $chk = $db->query("SELECT id, username FROM admins WHERE username = 'admin'");
        if ($chk->num_rows > 0) {
            $results[] = ['status' => 'ok', 'msg' => "Admin account found — password was already correct, no change needed."];
        } else {
            // Admin row doesn't exist — insert it
            $newStmt = $db->prepare("INSERT INTO admins (username, email, password, full_name, role) VALUES ('admin', 'admin@ridex.com', ?, 'RideX Chief Admin', 'Super Admin')");
            $newStmt->bind_param("s", $correctHash);
            if ($newStmt->execute()) {
                $results[] = ['status' => 'ok', 'msg' => "Admin account did not exist — created fresh with password 'admin123'."];
            } else {
                $results[] = ['status' => 'err', 'msg' => "Failed to insert admin: " . $db->error];
                $allOk = false;
            }
        }
    }
} else {
    $results[] = ['status' => 'err', 'msg' => "Failed to update admin password: " . $stmt->error];
    $allOk = false;
}

// ----------------------------------------------------------------
// 3. Verify admin login works with new hash
// ----------------------------------------------------------------
$verify = $db->query("SELECT password FROM admins WHERE username = 'admin'")->fetch_assoc();
if ($verify && password_verify('admin123', $verify['password'])) {
    $results[] = ['status' => 'ok', 'msg' => "Admin login verification PASSED — 'admin'/'admin123' will work correctly."];
} else {
    $results[] = ['status' => 'err', 'msg' => "Admin login verification FAILED — check password manually."];
    $allOk = false;
}

// ----------------------------------------------------------------
// 4. Self-delete after success
// ----------------------------------------------------------------
if ($allOk) {
    @unlink(__FILE__);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RideX Database Migration</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; }
        .card { max-width: 680px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 32px; border: 1px solid #334155; }
        h1   { color: #38bdf8; font-size: 22px; margin: 0 0 24px; }
        .item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #334155; }
        .item:last-child { border-bottom: none; }
        .badge-ok  { background: #16a34a; color: #fff; border-radius: 6px; padding: 3px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge-err { background: #dc2626; color: #fff; border-radius: 6px; padding: 3px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .msg  { font-size: 14px; color: #cbd5e1; line-height: 1.5; }
        .footer { margin-top: 24px; padding: 16px; background: <?= $allOk ? '#14532d' : '#450a0a' ?>; border-radius: 8px; font-size: 14px; }
        .footer strong { color: <?= $allOk ? '#4ade80' : '#f87171' ?>; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
<div class="card">
    <h1>⚙️ RideX Database Migration</h1>
    <?php foreach ($results as $r): ?>
    <div class="item">
        <span class="<?= $r['status'] === 'ok' ? 'badge-ok' : 'badge-err' ?>"><?= strtoupper($r['status']) ?></span>
        <span class="msg"><?= htmlspecialchars($r['msg']) ?></span>
    </div>
    <?php endforeach; ?>
    <div class="footer">
        <?php if ($allOk): ?>
            <strong>✅ All migrations completed successfully!</strong><br>
            This script has been automatically deleted for security.<br><br>
            <a href="admin/login.php">→ Go to Admin Login</a> &nbsp;|&nbsp;
            <a href="index.php">→ Go to RideX Main Site</a>
        <?php else: ?>
            <strong>❌ One or more migrations failed.</strong> Check the errors above. This script was NOT deleted.
        <?php endif; ?>
    </div>
</div>
</body>
</html>
