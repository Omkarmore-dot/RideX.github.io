<?php
// login.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Sign In to RideX";

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$db = getDB();
$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectTarget = $_POST['redirect'] ?? 'dashboard.php';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email address and password.";
    } else {
        $stmt = $db->prepare("SELECT id, full_name, email, password, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            if ($user['status'] === 'suspended') {
                $error = "Your account has been suspended. Please contact customer support.";
            } else {
                // Verify password (supports hashed and auto-upgrades if needed)
                $valid = password_verify($password, $user['password']) || ($password === $user['password']);

                if ($valid) {
                    // If stored password was plain, auto-upgrade to password_hash
                    if ($password === $user['password']) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmtUp = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmtUp->bind_param("si", $newHash, $user['id']);
                        $stmtUp->execute();
                    }

                    // Authenticate session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];

                    setFlash('success', "Welcome back, " . htmlspecialchars($user['full_name']) . "!");
                    
                    // Sanitize redirect target to avoid open redirects
                    $dest = (strpos($redirectTarget, '/') === 0 || strpos($redirectTarget, '.php') !== false) ? $redirectTarget : 'dashboard.php';
                    header("Location: " . $dest);
                    exit();
                } else {
                    $error = "Invalid email or password.";
                }
            }
        } else {
            $error = "No user account registered with this email address.";
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 50px; padding-bottom: 80px;">
    <div class="card" style="max-width: 450px; margin: 0 auto; box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 25px;">
            <div class="brand-icon" style="margin: 0 auto 12px; width: 48px; height: 48px;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">Sign In to RideX</h1>
            <p style="color: var(--text-muted); font-size: 14px;">Access your bookings, rentals, and shipments.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Quick Demo Credentials Hint Box -->
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 20px; font-size: 13px; color: #166534;">
            <strong><i class="fa-solid fa-circle-info"></i> Demo User Credentials:</strong><br>
            Email: <code>user@example.com</code><br>
            Password: <code>user123</code>
        </div>

        <form action="login.php" method="POST">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="form-group">
                <label><i class="fa-solid fa-envelope"></i> Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="user@example.com" value="<?= htmlspecialchars($_POST['email'] ?? 'user@example.com') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-key"></i> Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" value="user123" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top: 20px;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div class="auth-divider"><span>OR</span></div>

        <a href="google_auth.php?redirect=<?= urlencode($redirect) ?>" class="btn btn-google btn-lg btn-block">
            <svg viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            <span>Sign in with Google</span>
        </a>

        <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 14px;">
            Don't have an account? <a href="register.php" style="font-weight: 600;">Create Account</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
