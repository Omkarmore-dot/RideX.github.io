<?php
// config/razorpay.php

// Read Razorpay API keys from environment variables (fallback to placeholders if not set in XAMPP)
// In production, configure these via Apache SetEnv or php.ini, or secure .env files.
$razorpay_key_id = getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_TZet6pbCViUSR7';
$razorpay_key_secret = getenv('RAZORPAY_KEY_SECRET') ?: '5dr4h5UwaLnSvIAMDQVXKSEp';

define('RAZORPAY_KEY_ID', $razorpay_key_id);
define('RAZORPAY_KEY_SECRET', $razorpay_key_secret);
?>
