<?php
// google_auth.php
// Google Authentication Controller for RideX
require_once __DIR__ . '/config/db.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$db = getDB();
$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'dashboard.php');

// Handle Google Sign-in Verification / Authorization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'google_callback') {
    $email = trim($_POST['google_email'] ?? '');
    $name = trim($_POST['google_name'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid Google email address received.";
    } else {
        if (empty($name)) {
            $nameParts = explode('@', $email);
            $name = ucwords(str_replace(['.', '_', '-'], ' ', $nameParts[0]));
        }

        // Check if user exists with this email
        $stmt = $db->prepare("SELECT id, full_name, email, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            if ($user['status'] === 'suspended') {
                $error = "Your account has been suspended. Please contact customer support.";
            } else {
                // Log in existing user
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['auth_provider'] = 'google';

                setFlash('success', "Signed in with Google as " . htmlspecialchars($user['full_name']) . "!");
                header("Location: " . $redirect);
                exit();
            }
        } else {
            // Auto-register new Google user
            $randomPassword = bin2hex(random_bytes(16));
            $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
            $phone = "+1 555-0" . rand(100, 999);
            $address = "Google Authenticated User";

            $stmtInsert = $db->prepare("INSERT INTO users (full_name, email, phone, password, address, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmtInsert->bind_param("sssss", $name, $email, $phone, $hashedPassword, $address);

            if ($stmtInsert->execute()) {
                $newUserId = $stmtInsert->insert_id;

                // Welcome Notification
                createNotification(
                    $newUserId,
                    "Welcome to RideX!",
                    "You registered via Google Sign-In. You can now book rides, rent vehicles, or send courier parcels.",
                    "vehicles.php"
                );

                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['auth_provider'] = 'google';

                setFlash('success', "Welcome to RideX! Your account was created with Google.");
                header("Location: " . $redirect);
                exit();
            } else {
                $error = "Failed to register account via Google: " . $db->error;
            }
        }
    }
}

$pageTitle = "Sign in with Google";
include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 80px;">
    <div class="card" style="max-width: 480px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px 28px;">
        <!-- Google Header -->
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 12px; border: 1px solid #f1f5f9;">
                <svg viewBox="0 0 48 48" width="28" height="28">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
            </div>
            <h2 style="font-size: 22px; font-weight: 700; color: #1f2937; margin: 0 0 6px 0;">Sign in with Google</h2>
            <p style="color: #6b7280; font-size: 13px; margin: 0;">to continue to <strong>RideX</strong></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="font-size: 13px; padding: 10px 14px; margin-bottom: 20px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Choose Google Account list -->
        <div style="margin-bottom: 20px;">
            <span style="display: block; font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">Choose an account</span>

            <!-- Account 1: Existing Test User -->
            <form action="google_auth.php" method="POST" style="margin-bottom: 10px;">
                <input type="hidden" name="action" value="google_callback">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <input type="hidden" name="google_email" value="user@example.com">
                <input type="hidden" name="google_name" value="Alex Morgan">
                <button type="submit" style="width: 100%; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 10px; background: #ffffff; cursor: pointer; text-align: left; transition: all 0.2s ease;" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e5e7eb';">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #4f46e5; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px;">
                        A
                    </div>
                    <div style="flex: 1;">
                        <strong style="display: block; font-size: 14px; color: #111827;">Alex Morgan</strong>
                        <span style="font-size: 12px; color: #6b7280;">user@example.com</span>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color: #9ca3af; font-size: 12px;"></i>
                </button>
            </form>

            <!-- Account 2: Sarah Jenkins -->
            <form action="google_auth.php" method="POST" style="margin-bottom: 10px;">
                <input type="hidden" name="action" value="google_callback">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <input type="hidden" name="google_email" value="sarah.j@example.com">
                <input type="hidden" name="google_name" value="Sarah Jenkins">
                <button type="submit" style="width: 100%; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 10px; background: #ffffff; cursor: pointer; text-align: left; transition: all 0.2s ease;" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e5e7eb';">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #0284c7; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px;">
                        S
                    </div>
                    <div style="flex: 1;">
                        <strong style="display: block; font-size: 14px; color: #111827;">Sarah Jenkins</strong>
                        <span style="font-size: 12px; color: #6b7280;">sarah.j@example.com</span>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color: #9ca3af; font-size: 12px;"></i>
                </button>
            </form>
        </div>

        <!-- Or Use Another Google Account -->
        <div style="border-top: 1px solid #f1f5f9; padding-top: 16px; margin-top: 16px;">
            <span style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 10px;">Or enter any Google email:</span>
            <form action="google_auth.php" method="POST">
                <input type="hidden" name="action" value="google_callback">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="form-group" style="margin-bottom: 12px;">
                    <input type="email" name="google_email" class="form-control" placeholder="your.name@gmail.com" required style="font-size: 14px; padding: 10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <input type="text" name="google_name" class="form-control" placeholder="Your Display Name (Optional)" style="font-size: 14px; padding: 10px 14px;">
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="background: #1a73e8; border-color: #1a73e8;">
                    <i class="fa-brands fa-google"></i> Continue with this Google Account
                </button>
            </form>
        </div>

        <!-- Google Privacy Footer -->
        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #f3f4f6; text-align: center; font-size: 12px; color: #9ca3af; line-height: 1.5;">
            <i class="fa-solid fa-shield-halved" style="color: #10b981;"></i> Secure Google OAuth Authentication.<br>
            To continue, Google will share your name and email with RideX.
        </div>

        <div style="text-align: center; margin-top: 16px;">
            <a href="login.php" style="font-size: 13px; color: #6b7280; font-weight: 500;">
                <i class="fa-solid fa-arrow-left"></i> Back to standard login
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
