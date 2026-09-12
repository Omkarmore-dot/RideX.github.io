<?php
// register.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Create a RideX Account";

// If already logged in, redirect
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    // Validation
    if (empty($fullName) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $error = "Please fill in all mandatory fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already registered
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->bind_param("s", $email);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            $error = "An account with this email address already exists. Please sign in instead.";
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmtInsert = $db->prepare("INSERT INTO users (full_name, email, phone, password, address, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmtInsert->bind_param("sssss", $fullName, $email, $phone, $hashedPassword, $address);

            if ($stmtInsert->execute()) {
                $newUserId = $stmtInsert->insert_id;

                // Welcome Notification
                createNotification(
                    $newUserId, 
                    "Welcome to RideX!", 
                    "Your account has been created. Explore our vehicle fleet, book rides, rent vehicles, or dispatch couriers anytime.", 
                    "vehicles.php"
                );

                // Auto-login new user
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;

                setFlash('success', "Registration successful! Welcome aboard, {$fullName}.");
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Registration failed due to a server error: " . $db->error;
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 80px;">
    <div class="card" style="max-width: 540px; margin: 0 auto; box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 25px;">
            <div class="brand-icon" style="margin: 0 auto 12px; width: 48px; height: 48px;">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">Join RideX Today</h1>
            <p style="color: var(--text-muted); font-size: 14px;">Instant rides, self-drive rentals, and express deliveries.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+1 555-0199" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" class="form-control" placeholder="john.doe@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <div class="form-group">
                <label>Pickup / Residence Address (Optional)</label>
                <textarea name="address" class="form-control" placeholder="Your residential address for quick pickups"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top: 15px;">
                <i class="fa-solid fa-user-check"></i> Register Account
            </button>
        </form>

        <div class="auth-divider"><span>OR</span></div>

        <a href="google_auth.php" class="btn btn-google btn-lg btn-block">
            <svg viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            <span>Sign up with Google</span>
        </a>

        <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 14px;">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign In</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
