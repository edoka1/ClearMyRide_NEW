<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function json_response($success, $message, $redirect = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect]);
    exit;
}

function flash_and_redirect($type, $message, $back = null) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    if ($back === null) {
        $back = base_url('verify_otp.php');
    }
    header("Location: $back");
    exit;
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    if ($isAjax) json_response(false, 'Invalid security token.');
    else flash_and_redirect('error', 'Invalid security token.');
}

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    if ($isAjax) json_response(false, 'Email and OTP are required.');
    else flash_and_redirect('error', 'Email and OTP are required.', base_url('verify_otp.php?email=' . urlencode($email)));
}

if (!preg_match('/^\d{6}$/', $otp)) {
    if ($isAjax) json_response(false, 'OTP must be exactly 6 digits.');
    else flash_and_redirect('error', 'OTP must be exactly 6 digits.', base_url('verify_otp.php?email=' . urlencode($email)));
}

try {
    $stmt = $pdo->prepare("SELECT id, reset_otp, reset_otp_expires FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        if ($isAjax) json_response(false, 'Invalid request.');
        else flash_and_redirect('error', 'Invalid request.', base_url('forgot_password.php'));
    }

    $now = date('Y-m-d H:i:s');
    if ($user['reset_otp'] !== $otp || $user['reset_otp_expires'] < $now) {
        if ($isAjax) json_response(false, 'Invalid or expired verification code.');
        else flash_and_redirect('error', 'Invalid or expired verification code.', base_url('verify_otp.php?email=' . urlencode($email)));
    }

    $clear = $pdo->prepare("UPDATE users SET reset_otp = NULL, reset_otp_expires = NULL WHERE id = ?");
    $clear->execute([$user['id']]);

    $_SESSION['reset_email_verified'] = $user['id'];

    $redirectUrl = base_url('reset_password.php');

    if ($isAjax) {
        json_response(true, 'OTP verified. You can now reset your password.', $redirectUrl);
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'OTP verified. Please set a new password.'];
        header("Location: $redirectUrl");
        exit;
    }

} catch (Exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    if ($isAjax) json_response(false, 'An error occurred. Please try again.');
    else flash_and_redirect('error', 'An error occurred. Please try again.', base_url('forgot_password.php'));
}