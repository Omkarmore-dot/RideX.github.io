<?php
// admin/login.php
require_once __DIR__ . '/../config/db.php';

// A normal logged-in user must see No Access instead of the admin login form.
if (isLoggedIn() && !isAdminLoggedIn()) {
    enforceAdminAreaAccess();
}

// If already authenticated as admin, go to dashboard
if (isAdminLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both admin username and password.";
    } else {
        $stmt = $db->prepare("SELECT id, username, email, password, full_name, role FROM admins WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin) {
            $valid = password_verify($password, $admin['password']) || ($password === $admin['password']);

            if ($valid) {
                // If stored password was plain, auto-upgrade
                if ($password === $admin['password']) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUp = $db->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $stmtUp->bind_param("si", $newHash, $admin['id']);
                    $stmtUp->execute();
                }

                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];

                setFlash('success', "Welcome to RideX Control Center, " . htmlspecialchars($admin['full_name']) . "!");
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Incorrect administrator password.";
            }
        } else {
            $error = "No administrator found matching this username/email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Authentication | RideX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="card" style="width: 100%; max-width: 440px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); background: #ffffff;">
    <div style="text-align: center; margin-bottom: 25px;">
        <div style="width: 54px; height: 54px; border-radius: 12px; background: linear-gradient(135deg, #4f46e5, #0284c7); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 15px;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--dark); margin-bottom: 4px;">RideX Admin Portal</h1>
        <p style="color: var(--text-muted); font-size: 14px;">Authorized Staff & Fleet Management Only</p>
    </div>

    <?= getFlash() ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Demo Admin Credentials Callout -->
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-md); padding: 12px; margin-bottom: 20px; font-size: 13px; color: #1e40af;">
        <strong><i class="fa-solid fa-circle-info"></i> Default Admin Credentials:</strong><br>
        Username: <code>admin</code><br>
        Password: <code>admin123</code>
    </div>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label><i class="fa-solid fa-user-shield"></i> Username or Email</label>
            <input type="text" name="username" class="form-control" value="admin" placeholder="admin" required autofocus>
        </div>

        <div class="form-group">
            <label><i class="fa-solid fa-lock"></i> Password</label>
            <input type="password" name="password" class="form-control" value="admin123" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top: 15px;">
            <i class="fa-solid fa-shield-check"></i> Sign In to Control Center
        </button>
    </form>

    <div style="text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--border); font-size: 13px;">
        <a href="../index.php" style="color: var(--text-muted);"><i class="fa-solid fa-arrow-left"></i> Return to Main RideX Website</a>
    </div>
</div>

</body>
</html>
