<?php
// assets/app/submit_license.php
// Full file — includes PHPMailer (Gmail SMTP) sending for admin + client

// --- PHPMailer imports (file scope) ---
require_once __DIR__ . '/../../vendor/autoload.php'; // adjust path if vendor is elsewhere

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- Mail configuration (update these) ---
define('MAIL_USER', 'divineojo12345@gmail.com');         // your Gmail address
define('MAIL_PASS', 'jsozgcklgwzcesrz'); // your Gmail App Password (no spaces)
define('MAIL_FROM', 'support@clearmyride.com'); // visible From address
define('MAIL_FROM_NAME', 'ClearMyRide Support');
define('MAIL_ADMIN_TO', 'support@clearmyride.com'); // admin recipient

// Start session
session_start();

// minimal helper to set flash message and redirect
function flash_and_redirect($type, $message, $back = 'https://clearmyride.com/#form') {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header("Location: $back");
    exit;
}

// debug log helper
$logPath = __DIR__ . '/../../storage/logs/upload_debug.log';
if (!is_dir(dirname($logPath))) mkdir(dirname($logPath), 0755, true);
function dbg($msg) {
    global $logPath;
    @file_put_contents($logPath, date('Y-m-d H:i:s') . " - " . $msg . PHP_EOL, FILE_APPEND);
}

dbg("=== submit_license.php called ===");

// --- PHPMailer helper ---
/**
 * Send an email via Gmail SMTP (PHPMailer).
 * Returns true on success, false on failure (and logs error).
 */
function send_mail($to, $toName, $subject, $htmlBody, $plainBody = '') {
    global $logPath;

    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = 0; // change to 2 for verbose debug while testing

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

        $mail->send();
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " - send_mail success to {$to} subject={$subject}" . PHP_EOL, FILE_APPEND);
        return true;
    } catch (Exception $e) {
        $err = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " - send_mail FAILED to {$to}: " . $err . " | Exception: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        return false;
    }
}

// --- Begin request processing ---

// CSRF
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    dbg("CSRF failed. POST token: " . ($_POST['csrf_token'] ?? '[none]') . " SESSION token: " . ($_SESSION['csrf_token'] ?? '[none]'));
    flash_and_redirect('error', 'Invalid or missing CSRF token.');
}

require_once __DIR__ . '/db_connect.php';

// required fields
$required = ['fullName','email','phone','dob','licenseNumber','signature'];
foreach ($required as $f) {
    if (empty($_POST[$f])) {
        dbg("Missing required field: $f");
        flash_and_redirect('error', "Field '{$f}' is required.");
    }
}

// email validation
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    dbg("Invalid email: " . ($_POST['email'] ?? ''));
    flash_and_redirect('error', 'Please provide a valid email address.');
}

// sanitize
$fullName = trim($_POST['fullName']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$dob = $_POST['dob'];
$licenseNumber = trim($_POST['licenseNumber']);
$licenseExp = !empty($_POST['licenseExp']) ? $_POST['licenseExp'] : null;
$hasIssues = (isset($_POST['l-hasIssues']) && $_POST['l-hasIssues'] === 'yes') ? 1 : 0;
$signature = trim($_POST['signature']);
$consent = isset($_POST['consent']) ? 1 : 0;

if (!$consent) {
    dbg("Consent not given");
    flash_and_redirect('error', 'You must consent to allow us access to your driving record.');
}

// Log PHP upload limits for debugging
dbg("PHP ini: upload_max_filesize=" . ini_get('upload_max_filesize') . ", post_max_size=" . ini_get('post_max_size') . ", file_uploads=" . ini_get('file_uploads'));

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO license_requests
        (full_name,email,phone,dob,license_number,license_exp,has_issues,signature,consent)
        VALUES (:full_name,:email,:phone,:dob,:license_number,:license_exp,:has_issues,:signature,:consent)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':dob' => $dob,
        ':license_number' => $licenseNumber,
        ':license_exp' => $licenseExp,
        ':has_issues' => $hasIssues,
        ':signature' => $signature,
        ':consent' => $consent
    ]);
    $parentId = $pdo->lastInsertId();
    dbg("Inserted license_requests id=$parentId");

    // file uploads (licenseFiles[])
    if (!empty($_FILES['licenseFiles']) && isset($_FILES['licenseFiles']['name']) && is_array($_FILES['licenseFiles']['name'])) {
        dbg("FILES licenseFiles present: " . json_encode([
            'count' => count($_FILES['licenseFiles']['name']),
            'errors' => $_FILES['licenseFiles']['error']
        ]));

        $allowedTypes = ['application/pdf','image/jpeg','image/png','image/jpg'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        $uploadBase = __DIR__ . '/../../uploads/license/' . $parentId . '/';
        dbg("Upload base path: $uploadBase");
        if (!is_dir($uploadBase)) {
            if (!@mkdir($uploadBase, 0755, true)) {
                $err = error_get_last();
                dbg("mkdir failed for $uploadBase - " . json_encode($err));
            } else {
                dbg("Created upload dir $uploadBase");
            }
        }

        for ($i = 0; $i < count($_FILES['licenseFiles']['name']); $i++) {
            $error = $_FILES['licenseFiles']['error'][$i];
            $origName = $_FILES['licenseFiles']['name'][$i] ?? '';
            dbg("Processing file index $i name=$origName error=$error");

            if ($error !== UPLOAD_ERR_OK) {
                $errMsg = match ($error) {
                    UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
                    UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
                    UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
                    UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
                    UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
                    UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
                    UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
                    default => "UNKNOWN_UPLOAD_ERROR($error)"
                };
                dbg("Skipping file ($origName) - php upload error: $errMsg");
                continue;
            }

            $tmp = $_FILES['licenseFiles']['tmp_name'][$i];
            $size = $_FILES['licenseFiles']['size'][$i];

            $finfo_type = @mime_content_type($tmp);
            if ($finfo_type === false) $finfo_type = $_FILES['licenseFiles']['type'][$i] ?? '';
            dbg("tmp=$tmp size=$size mime=$finfo_type");

            if ($size > $maxSize) {
                dbg("Skipping file ($origName) - size $size > max $maxSize");
                continue;
            }
            if (!in_array($finfo_type, $allowedTypes)) {
                dbg("Skipping file ($origName) - mime $finfo_type not allowed");
                continue;
            }

            $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($origName));
            $dest = $uploadBase . $safeName;

            if (@move_uploaded_file($tmp, $dest)) {
                dbg("move_uploaded_file succeeded for $origName -> $dest");
                $sql2 = "INSERT INTO attachments (parent_type,parent_id,file_name,file_path,mime_type,file_size) VALUES ('license',:parent_id,:file_name,:file_path,:mime_type,:file_size)";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([
                    ':parent_id' => $parentId,
                    ':file_name' => $origName,
                    ':file_path' => $dest,
                    ':mime_type' => $finfo_type,
                    ':file_size' => $size
                ]);
                dbg("Inserted attachment record for file $origName, attachment id=" . $pdo->lastInsertId());
            } else {
                $lastErr = error_get_last();
                dbg("move_uploaded_file FAILED for $origName. error_get_last: " . json_encode($lastErr));
            }
        }
    } else {
        dbg("No licenseFiles[] provided in _FILES");
    }

    $pdo->commit();
    dbg("Transaction committed for license_requests id=$parentId");

    // --- SEND EMAILS ---

    // Timestamp for emails (server time)
    $submittedAt = date('Y-m-d H:i:s');

    // ===== ADMIN EMAIL: SHORT NOTIFICATION =====
    // Only notify admin that a license request was made: name, type, date/time
    $adminSubject = "New ClearMyRide LICENSE request — {$fullName}";
    $adminBody = "
        <div style=\"font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e6e9ee;border-radius:8px;overflow:hidden;\">
            <!-- Header -->
            <div style=\"background:#0A57FF;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:600;\">
                ClearMyRide — New License Request
            </div>

            <!-- Body -->
            <div style=\"padding:20px;color:#111827;font-size:14px;line-height:1.5;\">
                <p style=\"margin:0 0 12px 0;\">Hi team,</p>
                <p style=\"margin:0 0 12px 0;\">A new <strong>license request</strong> was submitted.</p>
                <table cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;margin-top:8px;border-collapse:collapse;\">
                    <tr>
                        <td style=\"padding:6px 0;font-weight:600;width:140px;color:#374151;\">Client name</td>
                        <td style=\"padding:6px 0;color:#111827;\">" . htmlspecialchars($fullName) . "</td>
                    </tr>
                    <tr>
                        <td style=\"padding:6px 0;font-weight:600;color:#374151;\">Request type</td>
                        <td style=\"padding:6px 0;color:#111827;\">License request</td>
                    </tr>
                    <tr>
                        <td style=\"padding:6px 0;font-weight:600;color:#374151;\">Submitted at</td>
                        <td style=\"padding:6px 0;color:#111827;\">{$submittedAt}</td>
                    </tr>
                </table>

                <p style=\"margin:16px 0 0 0;color:#6b7280;font-size:13px;\">No submission details are included here — please log in to the admin panel to view the full request.</p>

                <p style=\"margin:18px 0 0 0;\">
                    <a href=\"https://clearmyride.com/admin/login.php\" style=\"display:inline-block;padding:10px 14px;border-radius:6px;border:1px solid #0A57FF;background:#ffffff;color:#0A57FF;text-decoration:none;font-weight:600;\">Open request</a>
                </p>
            </div>

            <!-- Footer -->
            <div style=\"background:#f8fafc;padding:12px 20px;color:#6b7280;font-size:12px;text-align:center;\">
                ClearMyRide • support@clearmyride.com • <span style=\"white-space:nowrap;\">© " . date('Y') . " ClearMyRide</span>
            </div>
        </div>
    ";

    $adminSent = send_mail(MAIL_ADMIN_TO, 'ClearMyRide Admin', $adminSubject, $adminBody);

    // ===== CLIENT EMAIL: REDESIGNED BUT CONTENT PRESERVED =====
    $clientSubject = "Welcome to ClearMyRide — You’re Early to the Future of License Renewals";
    $clientBody = "
        <div style=\"font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:680px;margin:0 auto;border:1px solid #eef2ff;border-radius:8px;overflow:hidden;\">
            <!-- Header -->
            <div style=\"background:linear-gradient(90deg,#3BA7FF,#0A57FF);padding:18px 20px;color:#ffffff;font-size:18px;font-weight:700;\">
                Welcome to ClearMyRide
            </div>

            <!-- Body -->
            <div style=\"padding:22px;color:#111827;font-size:15px;line-height:1.6;\">
                <p style=\"margin:0 0 12px 0;\">Hey " . htmlspecialchars(explode(' ', $fullName)[0] ?? $fullName) . ",</p>

                <p style=\"margin:0 0 12px 0;\">Thanks for signing up with ClearMyRide — and welcome to our pilot group. You’re one of the first people testing a faster, smarter way to handle tags, citations, and license renewals.</p>

                <p style=\"margin:0 0 8px 0;font-weight:600;\">Here’s how it’ll go:</p>
                <ul style=\"margin:8px 0 12px 18px;padding:0;color:#374151;\">
                  <li>Our team will manually process your request while we finish building the automation dashboard.</li>
                  <li>You’ll get daily updates by text and email as we work through your request.</li>
                  <li>A separate email will follow once we’ve reviewed your form and assessed how urgent your request is — that one will include your specific next steps.</li>
                  <li>When it’s all wrapped up, we’ll send you a short feedback form. Your honest thoughts will help shape the public version of ClearMyRide.</li>
                </ul>

                <p style=\"margin:0 0 14px 0;color:#6b7280;font-size:13px;\">We take your privacy seriously. You can read our <a href=\"https://clearmyride.com/privacy\" style=\"color:#0A57FF;text-decoration:none;font-weight:600;\">Privacy Notice</a> to see how we collect, use, and protect your data.</p>

                <p style=\"margin:0 0 6px 0;\">Appreciate you being part of the journey. You’re helping us build something smoother for every driver out there.</p>

                <p style=\"margin:14px 0 0 0;font-weight:700;color:#111827;\">Talk soon,<br>The ClearMyRide Team</p>

                <p style=\"margin:6px 0 0 0;color:#0A57FF;font-weight:600;\"><a href=\"mailto:support@clearmyride.com\" style=\"color:inherit;text-decoration:none;\">support@clearmyride.com</a></p>
            </div>

            <!-- Footer -->
            <div style=\"background:#f8fafc;padding:12px 20px;color:#6b7280;font-size:13px;text-align:center;\">
                Your ride, renewed — without the stress. • <span style=\"white-space:nowrap;\">© " . date('Y') . " ClearMyRide</span>
            </div>
        </div>
    ";

    $clientSent = send_mail($email, $fullName, $clientSubject, $clientBody);

    dbg("Admin email sent? " . ($adminSent ? 'yes' : 'no'));
    dbg("Client email sent? " . ($clientSent ? 'yes' : 'no'));

    flash_and_redirect('success', 'License renewal request submitted successfully. We will contact you with updates.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    dbg("Exception during submit_license: " . $e->getMessage());
    flash_and_redirect('error', 'An error occurred while processing your license request. Please try again later.');
}
