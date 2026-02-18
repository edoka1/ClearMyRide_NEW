<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';          // ← absolute URL helper
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// ------------------------------------------------------------
// 1. Helper functions
// ------------------------------------------------------------
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function json_response($success, $message, $redirect = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect]);
    exit;
}

function flash_and_redirect($type, $message, $back = null) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    if ($back === null) {
        $back = base_url('forgot_password.php');
    }
    header("Location: $back");
    exit;
}

// ------------------------------------------------------------
// 2. Mail configuration & sender
// ------------------------------------------------------------
define('MAIL_USER', 'divineojo12345@gmail.com');        // use your own
define('MAIL_PASS', 'jsozgcklgwzcesrz');                // use your own
define('MAIL_FROM', 'support@clearmyride.com');
define('MAIL_FROM_NAME', 'ClearMyRide Support');

function send_otp_email($to, $toName, $otp) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your ClearMyRide Password Reset Code';
        $mail->Body    = "
            <div style='font-family:Inter,sans-serif;max-width:500px;margin:0 auto;border:1px solid #eef2ff;border-radius:8px;'>
                <div style='background:#0A57FF;padding:16px 20px;color:white;font-size:18px;font-weight:600;'>
                    ClearMyRide Password Reset
                </div>
                <div style='padding:20px;color:#111827;'>
                    <p>Hello,</p>
                    <p>We received a request to reset your password. Use the verification code below to proceed. This code will expire in <strong>15 minutes</strong>.</p>
                    <div style='background:#f4f7fb;border-radius:8px;padding:20px;text-align:center;margin:20px 0;'>
                        <span style='font-size:32px;font-weight:700;letter-spacing:8px;color:#0A57FF;'>{$otp}</span>
                    </div>
                    <p>If you didn't request this, you can safely ignore this email.</p>
                    <p style='margin-top:20px;font-size:13px;color:#6b7280;'>ClearMyRide • support@clearmyride.com</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Your ClearMyRide password reset code is: $otp";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP email failed to $to: " . $e->getMessage());
        return false;
    }
}

// ------------------------------------------------------------
// 3. Process request
// ------------------------------------------------------------
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    if ($isAjax) json_response(false, 'Invalid security token. Please refresh the page.');
    else flash_and_redirect('error', 'Invalid security token.');
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if ($isAjax) json_response(false, 'Please enter a valid email address.');
    else flash_and_redirect('error', 'Please enter a valid email address.');
}

try {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $successMsg = 'If that email is registered, a verification code has been sent.';

    if ($user) {
        $otp = sprintf("%06d", random_int(0, 999999));
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $update = $pdo->prepare("UPDATE users SET reset_otp = ?, reset_otp_expires = ? WHERE id = ?");
        $update->execute([$otp, $expires, $user['id']]);

        send_otp_email($email, $user['full_name'], $otp);
    }

    $redirectUrl = base_url('verify_otp.php?email=' . urlencode($email));

    if ($isAjax) {
        json_response(true, $successMsg, $redirectUrl);
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => $successMsg];
        header("Location: $redirectUrl");
        exit;
    }

} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    if ($isAjax) json_response(false, 'An error occurred. Please try again later.');
    else flash_and_redirect('error', 'An error occurred. Please try again later.');
}