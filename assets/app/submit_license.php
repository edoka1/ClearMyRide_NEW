<?php
// assets/app/submit_license.php
session_start();
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_helper.php';

header('Content-Type: application/json');

function json_response($success, $message, $redirect = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect]);
    exit;
}

function flash_and_redirect($type, $message, $back = 'new-request.php') {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . base_url($back));
    exit;
}

$logPath = __DIR__ . '/../../storage/logs/upload_debug.log';
if (!is_dir(dirname($logPath))) mkdir(dirname($logPath), 0755, true);
function dbg($msg) { global $logPath; @file_put_contents($logPath, date('Y-m-d H:i:s') . " - " . $msg . PHP_EOL, FILE_APPEND); }

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    if ($isAjax) {
        json_response(false, 'Please login to continue');
    } else {
        flash_and_redirect('error', 'Please login to continue', 'login.php');
    }
}
$user = $auth->getCurrentUser();
if (!$user) {
    if ($isAjax) {
        json_response(false, 'User not found');
    } else {
        flash_and_redirect('error', 'User not found', 'logout.php');
    }
}

dbg("=== submit_license.php called ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        json_response(false, 'Invalid request method.');
    } else {
        flash_and_redirect('error', 'Invalid request method.', 'new-request.php');
    }
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    if ($isAjax) {
        json_response(false, 'Invalid or missing CSRF token.');
    } else {
        flash_and_redirect('error', 'Invalid or missing CSRF token.', 'new-request.php');
    }
}

require_once __DIR__ . '/db_connect.php';

$required = ['fullName','email','phone','dob','licenseNumber','signature'];
foreach ($required as $f) {
    if (empty($_POST[$f])) {
        if ($isAjax) {
            json_response(false, "Field '{$f}' is required.");
        } else {
            flash_and_redirect('error', "Field '{$f}' is required.", 'new-request.php');
        }
    }
}
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    if ($isAjax) {
        json_response(false, 'Please provide a valid email address.');
    } else {
        flash_and_redirect('error', 'Please provide a valid email address.', 'new-request.php');
    }
}

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
    if ($isAjax) {
        json_response(false, 'You must consent to allow us access to your driving record.');
    } else {
        flash_and_redirect('error', 'You must consent to allow us access to your driving record.', 'new-request.php');
    }
}

try {
    $pdo->beginTransaction();

    // 1. INSERT INTO customer_requests FIRST (status = 'Pending')
    $requestData = [
        'license_number' => $licenseNumber,
        'license_exp' => $licenseExp,
        'has_issues' => $hasIssues
    ];
    $stmt = $pdo->prepare("
        INSERT INTO customer_requests (user_id, request_type, request_data, status, created_at)
        VALUES (:user_id, 'license', :request_data, 'Pending', NOW())
    ");
    $stmt->execute([
        ':user_id' => $user['id'],
        ':request_data' => json_encode($requestData)
    ]);
    $customerRequestId = $pdo->lastInsertId();
    dbg("Inserted customer_requests id=$customerRequestId");

    // 2. INSERT INTO license_requests WITH customer_request_id
    $sql = "INSERT INTO license_requests
            (customer_request_id, full_name, email, phone, dob, license_number, license_exp, has_issues, signature, consent, user_id)
            VALUES (:cr_id, :full_name, :email, :phone, :dob, :license_number, :license_exp, :has_issues, :signature, :consent, :user_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cr_id' => $customerRequestId,
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':dob' => $dob,
        ':license_number' => $licenseNumber,
        ':license_exp' => $licenseExp,
        ':has_issues' => $hasIssues,
        ':signature' => $signature,
        ':consent' => $consent,
        ':user_id' => $user['id']
    ]);
    $licenseRequestId = $pdo->lastInsertId();
    dbg("Inserted license_requests id=$licenseRequestId with customer_request_id=$customerRequestId");

    // 3. File uploads
    if (!empty($_FILES['licenseFiles']) && isset($_FILES['licenseFiles']['name']) && is_array($_FILES['licenseFiles']['name'])) {
        $allowedTypes = ['application/pdf','image/jpeg','image/png','image/jpg'];
        $maxSize = 10 * 1024 * 1024;
        $uploadBase = __DIR__ . '/../../uploads/license/' . $licenseRequestId . '/';
        if (!is_dir($uploadBase)) mkdir($uploadBase, 0755, true);

        for ($i = 0; $i < count($_FILES['licenseFiles']['name']); $i++) {
            if ($_FILES['licenseFiles']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $_FILES['licenseFiles']['tmp_name'][$i];
            $size = $_FILES['licenseFiles']['size'][$i];
            $origName = $_FILES['licenseFiles']['name'][$i];
            $mime = mime_content_type($tmp) ?: $_FILES['licenseFiles']['type'][$i];
            if ($size > $maxSize) continue;
            if (!in_array($mime, $allowedTypes)) continue;
            $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($origName));
            $dest = $uploadBase . $safeName;
            if (move_uploaded_file($tmp, $dest)) {
                $stmt2 = $pdo->prepare("
                    INSERT INTO attachments (parent_type, parent_id, file_name, file_path, mime_type, file_size)
                    VALUES ('license', :parent_id, :file_name, :file_path, :mime_type, :file_size)
                ");
                $stmt2->execute([
                    ':parent_id' => $licenseRequestId,
                    ':file_name' => $origName,
                    ':file_path' => $dest,
                    ':mime_type' => $mime,
                    ':file_size' => $size
                ]);
                dbg("Uploaded file $origName");
            }
        }
    }

    $pdo->commit();
    dbg("Transaction committed.");

    // ---------- SEND EMAILS (with error logging only) ----------
    try {
        // Customer confirmation
          $customerLink = base_url("dashboard.php");
        $subject = 'License Request Received';
        $body = "
            <h2>Thank you for your request</h2>
            <p>Your driver's license request #{$customerRequestId} has been submitted successfully and is now <strong>Pending</strong>.</p>
            <p>You will receive updates as we process it.</p>
            <p><a href='{$customerLink}'>View your request in the dashboard</a></p>
        ";
        send_customer_email($email, $fullName, $subject, $body);

        // Admin notification
        $adminSubject = 'New License Request Submitted';
        $adminBody = "
            <h2>New License Request</h2>
            <p>A new license request has been submitted.</p>
            <ul>
                <li><strong>Request ID:</strong> #{$customerRequestId}</li>
                <li><strong>Customer:</strong> {$fullName} ({$email})</li>
                <li><strong>License #:</strong> {$licenseNumber}</li>
                <li><strong>Submitted:</strong> " . date('Y-m-d H:i') . "</li>
            </ul>
            <p><a href='" . base_url("admin/dashboard.php") . "'>Go to admin dashboard</a></p>
        ";
        notify_admins($adminSubject, $adminBody);
    } catch (Exception $e) {
        // Log email error but do not interrupt success response
        dbg("Email sending failed after successful submission: " . $e->getMessage());
        error_log("Email sending failed: " . $e->getMessage());
    }

    // Determine redirect destination
    $redirectUrl = base_url('dashboard.php'); // default
    if (isset($_POST['source']) && $_POST['source'] === 'dashboard') {
        $redirectUrl = base_url('license-requests.php');
    }

    if ($isAjax) {
        json_response(true, 'License request submitted successfully.', $redirectUrl);
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'License request submitted successfully.'];
        header('Location: ' . $redirectUrl);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    dbg("Exception: " . $e->getMessage());
    if ($isAjax) {
        json_response(false, 'An error occurred. Please try again later.');
    } else {
        flash_and_redirect('error', 'An error occurred. Please try again later.', 'new-request.php');
    }
}