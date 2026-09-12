<?php
// profile.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "My Profile & Settings";

requireLogin();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$db = getDB();

$error = '';
$success = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($fullName) || empty($phone)) {
        $error = "Full name and phone number cannot be blank.";
    } else {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $fullName, $phone, $address, $userId);
        if ($stmt->execute()) {
            setFlash('success', 'Profile details updated successfully!');
            header("Location: profile.php");
            exit();
        } else {
            $error = "Failed to update profile: " . $db->error;
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "Please fill in all password fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Fetch current password hash from DB
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();

        if (!password_verify($currentPassword, $userRow['password']) && ($currentPassword !== $userRow['password'])) {
            $error = "Incorrect current password.";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUp = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtUp->bind_param("si", $newHash, $userId);
            if ($stmtUp->execute()) {
                createNotification($userId, "Security Alert: Password Changed", "Your account password was updated successfully. If this wasn't you, contact support immediately.", "profile.php");
                setFlash('success', 'Password updated successfully!');
                header("Location: profile.php");
                exit();
            } else {
                $error = "Failed to update password: " . $db->error;
            }
        }
    }
}

// Reload current user details
$currentUser = getCurrentUser();

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 28px; font-weight: 800; color: var(--dark);">My Account Profile</h1>
        <p style="color: var(--text-muted);">Manage your personal details and security preferences.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 30px; align-items: start;">
        <!-- Left: Profile Details -->
        <div class="card">
            <h3 class="card-title"><i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Personal Details</h3>
            
            <form action="profile.php" method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled title="Email cannot be changed directly for security reasons">
                        <small style="color: var(--text-muted); font-size: 11px;">Contact support to alter email address.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" class="form-control" value="<?= date('M d, Y', strtotime($currentUser['created_at'])) ?>" disabled>
                    </div>
                </div>

                <div class="form-group">
                    <label>Default Address (for Pickups & Deliveries)</label>
                    <textarea name="address" class="form-control" placeholder="Street, apartment, city, and postal code"><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </form>
        </div>

        <!-- Right: Change Password -->
        <div class="card">
            <h3 class="card-title"><i class="fa-solid fa-lock" style="color: var(--accent);"></i> Change Password</h3>

            <form action="profile.php" method="POST">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-outline btn-block"><i class="fa-solid fa-key"></i> Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
