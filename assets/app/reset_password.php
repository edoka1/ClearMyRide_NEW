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
        $back = base_url('reset_password.php');
    }
    header("Location: $back");
    exit;
}

// 1. Check session (user must have verified OTP)
if (empty($_SESSION['reset_email_verified'])) {
    if ($isAjax) json_response(false, 'Session expired. Please request a new OTP.', base_url('forgot_password.php'));
    else flash_and_redirect('error', 'Session expired.', base_url('forgot_password.php'));
}

// 2. CSRF validation
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    if ($isAjax) json_response(false, 'Invalid security token.');
    else flash_and_redirect('error', 'Invalid security token.');
}

// 3. Password validation
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if (strlen($password) < 8) {
    if ($isAjax) json_response(false, 'Password must be at least 8 characters.');
    else flash_and_redirect('error', 'Password must be at least 8 characters.');
}
if (!preg_match('/(?=.*[A-Za-z])(?=.*\d)/', $password)) {
    if ($isAjax) json_response(false, 'Password must contain both letters and numbers.');
    else flash_and_redirect('error', 'Password must contain both letters and numbers.');
}
if ($password !== $confirm) {
    if ($isAjax) json_response(false, 'Passwords do not match.');
    else flash_and_redirect('error', 'Passwords do not match.');
}

try {
    $userId = $_SESSION['reset_email_verified'];
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // ✅ CORRECT COLUMN NAME: password_hash
    $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hashed, $userId]);

    // Clear the session flag
    unset($_SESSION['reset_email_verified']);

    $redirectUrl = base_url('login.php');

    if ($isAjax) {
        json_response(true, 'Password reset successful! You can now log in.', $redirectUrl);
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password reset successful!'];
        header("Location: $redirectUrl");
        exit;
    }

} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    if ($isAjax) json_response(false, 'An error occurred. Please try again later.');
    else flash_and_redirect('error', 'An error occurred. Please try again later.');
}