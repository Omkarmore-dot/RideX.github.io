<?php
// logout.php
require_once __DIR__ . '/config/db.php';

unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);

setFlash('success', 'You have been logged out successfully.');
header("Location: login.php");
exit();
?>
