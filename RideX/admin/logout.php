<?php
// admin/logout.php
require_once __DIR__ . '/../config/db.php';

unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

setFlash('success', 'Admin session terminated successfully.');
header("Location: login.php");
exit();
?>
