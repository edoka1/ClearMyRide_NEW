<?php
// assets/app/submit_vehicle.php
// Full file — includes PHPMailer (Gmail SMTP) sending for admin + client

// --- PHPMailer imports (file scope) ---
require_once __DIR__ . '/../../vendor/autoload.php'; // adjust path if vendor is elsewhere

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- Mail configuration (update these if needed) ---
define('MAIL_USER', 'divineojo12345@gmail.com');         // your Gmail address
define('MAIL_PASS', 'jsozgcklgwzcesrz'); // your Gmail App Password (no spaces)
define('MAIL_FROM', 'support@clearmyride.com'); // visible From address
define('MAIL_FROM_NAME', 'ClearMyRide Support');
define('MAIL_ADMIN_TO', 'support@clearmyride.com'); // admin recipient

// Start session (only once)
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

// Logging start
dbg("=== submit_vehicle.php called ===");
dbg('_SERVER: ' . json_encode([
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? '',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? ''
]));

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
        $mail->SMTPDebug  = 0; // set to 2 for verbose debug (don't enable in production)

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

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dbg("Invalid request method: " . ($_SERVER['REQUEST_METHOD'] ?? ''));
    flash_and_redirect('error', 'Invalid request method.');
}

// CSRF check
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    dbg("CSRF failed. POST token: " . ($_POST['csrf_token'] ?? '[none]') . " SESSION token: " . ($_SESSION['csrf_token'] ?? '[none]'));
    flash_and_redirect('error', 'Invalid or missing CSRF token.');
}

// require DB connection
require_once __DIR__ . '/db_connect.php';

// Basic required fields (server-side)
$required = ['fullName','email','phone','dob','plate','renewWhen','delivery','referral'];
foreach ($required as $f) {
    if (empty($_POST[$f])) {
        dbg("Missing required field: $f");
        flash_and_redirect('error', "Field '{$f}' is required.");
    }
}

// validate email
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    dbg("Invalid email: " . ($_POST['email'] ?? ''));
    flash_and_redirect('error', 'Please provide a valid email address.');
}

// collect + sanitize
$fullName = trim($_POST['fullName']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$dob = $_POST['dob'];
$plate = trim($_POST['plate']);
$vin = !empty($_POST['vin']) ? trim($_POST['vin']) : null;
$regExp = !empty($_POST['regExp']) ? $_POST['regExp'] : null;
$renewWhen = !empty($_POST['renewWhen']) ? $_POST['renewWhen'] : null;
$hasIssues = (isset($_POST['v-hasIssues']) && $_POST['v-hasIssues'] === 'yes') ? 1 : 0;
$delivery = !empty($_POST['delivery']) ? $_POST['delivery'] : null;
$referral = !empty($_POST['referral']) ? $_POST['referral'] : null;
$consent = isset($_POST['consent']) ? 1 : 0;

// --- Maryland plate validation (normalize + enforce rules) ---
$plateClean = strtoupper(trim($plate ?? ''));

// disallow I, O, Q for standard plates
if (preg_match('/[IOQ]/i', $plateClean)) {
    dbg("Invalid plate characters detected: $plateClean");
    flash_and_redirect('error', 'Plate contains invalid letters for Maryland standard plates (I, O, Q are not allowed).');
}

// allow letters, numbers, spaces, hyphens; require 4-10 chars; require at least one letter and one digit
if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9\s\-]{4,10}$/', $plateClean)) {
    dbg("Plate failed Maryland format check: $plateClean");
    flash_and_redirect('error', 'Please provide a valid Maryland plate (must include both letters and numbers, e.g. ABC-1234).');
}

// normalize for DB storage (optional: remove spaces/hyphens if you prefer)
$plateForDb = $plateClean;

// consent required
if (!$consent) {
    dbg("Consent not given");
    flash_and_redirect('error', 'You must consent to allow us access to your MVA records.');
}

// Log PHP upload limits for debugging
dbg("PHP ini: upload_max_filesize=" . ini_get('upload_max_filesize') . ", post_max_size=" . ini_get('post_max_size') . ", file_uploads=" . ini_get('file_uploads'));

// Insert into DB using transaction
try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO vehicle_requests
        (full_name,email,phone,dob,plate,vin,reg_exp,renew_when,has_issues,delivery_method,referral,consent)
        VALUES (:full_name,:email,:phone,:dob,:plate,:vin,:reg_exp,:renew_when,:has_issues,:delivery_method,:referral,:consent)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':dob' => $dob,
        ':plate' => $plateForDb,
        ':vin' => $vin,
        ':reg_exp' => $regExp,
        ':renew_when' => $renewWhen,
        ':has_issues' => $hasIssues,
        ':delivery_method' => $delivery,
        ':referral' => $referral,
        ':consent' => $consent
    ]);
    $parentId = $pdo->lastInsertId();
    dbg("Inserted vehicle_requests id=$parentId");

    // handle file uploads: input name vehicleFiles[]
    if (!empty($_FILES['vehicleFiles']) && isset($_FILES['vehicleFiles']['name']) && is_array($_FILES['vehicleFiles']['name'])) {
        dbg("FILES vehicleFiles present: " . json_encode([
            'count' => count($_FILES['vehicleFiles']['name']),
            'errors' => $_FILES['vehicleFiles']['error']
        ]));

        $allowedTypes = ['application/pdf','image/jpeg','image/png','image/jpg'];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        // uploads path at project root: /uploads/vehicle/<id>/
        $uploadBase = __DIR__ . '/../../uploads/vehicle/' . $parentId . '/';
        dbg("Upload base path: $uploadBase");
        if (!is_dir($uploadBase)) {
            if (!@mkdir($uploadBase, 0755, true)) {
                $err = error_get_last();
                dbg("mkdir failed for $uploadBase - " . json_encode($err));
                // keep going but don't attempt move if no dir
            } else {
                dbg("Created upload dir $uploadBase");
            }
        }

        for ($i = 0; $i < count($_FILES['vehicleFiles']['name']); $i++) {
            $error = $_FILES['vehicleFiles']['error'][$i];
            $origName = $_FILES['vehicleFiles']['name'][$i] ?? '';
            dbg("Processing file index $i name=$origName error=$error");

            if ($error !== UPLOAD_ERR_OK) {
                // log the php upload error code
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

            $tmp = $_FILES['vehicleFiles']['tmp_name'][$i];
            $size = $_FILES['vehicleFiles']['size'][$i];

            // mime detection
            $finfo_type = @mime_content_type($tmp);
            if ($finfo_type === false) $finfo_type = $_FILES['vehicleFiles']['type'][$i] ?? '';
            dbg("tmp=$tmp size=$size mime=$finfo_type");

            if ($size > $maxSize) {
                dbg("Skipping file ($origName) - size $size > max $maxSize");
                continue;
            }
            if (!in_array($finfo_type, $allowedTypes)) {
                dbg("Skipping file ($origName) - mime $finfo_type not in allowed list");
                continue;
            }

            $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($origName));
            $dest = $uploadBase . $safeName;

            // final move
            if (@move_uploaded_file($tmp, $dest)) {
                dbg("move_uploaded_file succeeded for $origName -> $dest");
                $sql2 = "INSERT INTO attachments (parent_type,parent_id,file_name,file_path,mime_type,file_size) VALUES ('vehicle',:parent_id,:file_name,:file_path,:mime_type,:file_size)";
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
        dbg("No vehicleFiles[] provided in _FILES");
    }

    $pdo->commit();
    dbg("Transaction committed for vehicle_requests id=$parentId");

    // --- SEND EMAILS ---

    // Timestamp for emails (server time)
    $submittedAt = date('Y-m-d H:i:s');

    // ===== ADMIN EMAIL: SHORT NOTIFICATION =====
    // Only notify admin that a vehicle request was made: name, type, date/time
    $adminSubject = "New ClearMyRide VEHICLE request — {$fullName}";
    $adminBody = "
        <div style=\"font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e6e9ee;border-radius:8px;overflow:hidden;\">
            <!-- Header -->
            <div style=\"background:#0A57FF;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:600;\">
                ClearMyRide — New Vehicle Request
            </div>

            <!-- Body -->
            <div style=\"padding:20px;color:#111827;font-size:14px;line-height:1.5;\">
                <p style=\"margin:0 0 12px 0;\">Hi team,</p>
                <p style=\"margin:0 0 12px 0;\">A new <strong>vehicle request</strong> was submitted.</p>
                <table cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;margin-top:8px;border-collapse:collapse;\">
                    <tr>
                        <td style=\"padding:6px 0;font-weight:600;width:140px;color:#374151;\">Client name</td>
                        <td style=\"padding:6px 0;color:#111827;\">" . htmlspecialchars($fullName) . "</td>
                    </tr>
                    <tr>
                        <td style=\"padding:6px 0;font-weight:600;color:#374151;\">Request type</td>
                        <td style=\"padding:6px 0;color:#111827;\">Vehicle request</td>
                    </tr>
                    <tr>
                        <td style=\"padding:6px 0;font-weight:600;color:#374151;\">Submitted at</td>
                        <td style=\"padding:6px 0;color:#111827;\">{$submittedAt}</td>
                    </tr>
                </table>

                <p style=\"margin:16px 0 0 0;color:#6b7280;font-size:13px;\">No submission details are included here — please log in to the admin panel to view the full request..</p>

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
    $clientSubject = "Welcome to ClearMyRide — You’re Early to the Future of Tag Renewals";
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
                  <li>You’ll get daily updates by text and email as we work through your citations or renewal.</li>
                  <li>A separate email will follow once we’ve reviewed your form and assessed how urgent your renewal is — that one will include your specific next steps.</li>
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

    // Final user redirect
    flash_and_redirect('success', 'Vehicle request submitted successfully. We will contact you by email with status updates.');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    dbg("Exception during submit_vehicle: " . $e->getMessage());
    flash_and_redirect('error', 'An error occurred while processing your request. Please try again later.');
}
