<?php
// assets/app/mail_helper.php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get all admin email addresses from the admin_users table.
 * @return array
 */
function get_admin_emails() {
    global $pdo;
    // Remove the 'active' condition – table doesn't have that column
    $stmt = $pdo->query("SELECT email FROM admin_users");
    $emails = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emails[] = $row['email'];
    }
    if (empty($emails)) {
        error_log("No admin emails found in admin_users table.");
    }
    return $emails;
}

/**
 * Send an email to all active admins.
 * @param string $subject
 * @param string $body (HTML)
 * @param string $altBody (plain text fallback)
 * @return bool
 */
function notify_admins($subject, $body, $altBody = '') {
    $admins = get_admin_emails();
    if (empty($admins)) {
        error_log("Cannot send admin notification: no admin emails.");
        return false;
    }

    // PHPMailer configuration – use the same credentials as in forgot_password.php
    define('MAIL_USER', 'divineojo12345@gmail.com');   // ← REPLACE WITH YOUR SMTP USER
    define('MAIL_PASS', 'jsozgcklgwzcesrz');           // ← REPLACE WITH YOUR SMTP PASSWORD
    define('MAIL_FROM', 'support@clearmyride.com');
    define('MAIL_FROM_NAME', 'ClearMyRide Admin');

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

        // Add all admins as BCC (so they don't see each other)
        foreach ($admins as $adminEmail) {
            $mail->addBCC($adminEmail);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Admin notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send an email to a customer.
 * @param string $to      Recipient email
 * @param string $toName  Recipient name
 * @param string $subject
 * @param string $body    HTML body
 * @param string $altBody Plain text fallback
 * @return bool
 */
function send_customer_email($to, $toName, $subject, $body, $altBody = '') {
    // Reuse PHPMailer configuration from notify_admins
    define('MAIL_USER', 'divineojo12345@gmail.com');   // ← REPLACE WITH YOUR SMTP USER
    define('MAIL_PASS', 'jsozgcklgwzcesrz');           // ← REPLACE WITH YOUR SMTP PASSWORD
    define('MAIL_FROM', 'support@clearmyride.com');
    define('MAIL_FROM_NAME', 'ClearMyRide');

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
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Customer email failed to $to: " . $e->getMessage());
        return false;
    }
}