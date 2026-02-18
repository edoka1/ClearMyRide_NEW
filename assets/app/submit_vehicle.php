<?php
// assets/app/submit_vehicle.php
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

dbg("=== submit_vehicle.php called ===");

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

// Validate required fields
$required = ['fullName','email','phone','dob','plate','renewWhen','delivery','referral'];
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

// Sanitize
$fullName = trim($_POST['fullName']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$dob = $_POST['dob'];
$plate = strtoupper(trim($_POST['plate']));
$vin = !empty($_POST['vin']) ? trim($_POST['vin']) : null;
$regExp = !empty($_POST['regExp']) ? $_POST['regExp'] : null;
$renewWhen = $_POST['renewWhen'] ?? null;
$hasIssues = (isset($_POST['v-hasIssues']) && $_POST['v-hasIssues'] === 'yes') ? 1 : 0;
$delivery = $_POST['delivery'] ?? null;
$referral = $_POST['referral'] ?? null;
$consent = isset($_POST['consent']) ? 1 : 0;

// Maryland plate validation
if (preg_match('/[IOQ]/i', $plate)) {
    if ($isAjax) {
        json_response(false, 'Plate contains invalid letters (I, O, Q not allowed).');
    } else {
        flash_and_redirect('error', 'Plate contains invalid letters (I, O, Q not allowed).', 'new-request.php');
    }
}
if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9\s\-]{4,10}$/', $plate)) {
    if ($isAjax) {
        json_response(false, 'Please provide a valid Maryland plate (e.g. ABC-1234).');
    } else {
        flash_and_redirect('error', 'Please provide a valid Maryland plate (e.g. ABC-1234).', 'new-request.php');
    }
}
if (!$consent) {
    if ($isAjax) {
        json_response(false, 'You must consent to allow us access to your MVA records.');
    } else {
        flash_and_redirect('error', 'You must consent to allow us access to your MVA records.', 'new-request.php');
    }
}

try {
    $pdo->beginTransaction();

    // 1. INSERT INTO customer_requests FIRST (status = 'Pending')
    $requestData = [
        'plate' => $plate,
        'vin' => $vin,
        'reg_exp' => $regExp,
        'renew_when' => $renewWhen,
        'has_issues' => $hasIssues,
        'delivery_method' => $delivery
    ];
    $stmt = $pdo->prepare("
        INSERT INTO customer_requests (user_id, request_type, request_data, status, created_at)
        VALUES (:user_id, 'vehicle', :request_data, 'Pending', NOW())
    ");
    $stmt->execute([
        ':user_id' => $user['id'],
        ':request_data' => json_encode($requestData)
    ]);
    $customerRequestId = $pdo->lastInsertId();
    dbg("Inserted customer_requests id=$customerRequestId");

    // 2. INSERT INTO vehicle_requests WITH customer_request_id
    $sql = "INSERT INTO vehicle_requests
            (customer_request_id, full_name, email, phone, dob, plate, vin, reg_exp, renew_when, has_issues, delivery_method, referral, consent, user_id)
            VALUES (:cr_id, :full_name, :email, :phone, :dob, :plate, :vin, :reg_exp, :renew_when, :has_issues, :delivery_method, :referral, :consent, :user_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cr_id' => $customerRequestId,
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':dob' => $dob,
        ':plate' => $plate,
        ':vin' => $vin,
        ':reg_exp' => $regExp,
        ':renew_when' => $renewWhen,
        ':has_issues' => $hasIssues,
        ':delivery_method' => $delivery,
        ':referral' => $referral,
        ':consent' => $consent,
        ':user_id' => $user['id']
    ]);
    $vehicleRequestId = $pdo->lastInsertId();
    dbg("Inserted vehicle_requests id=$vehicleRequestId with customer_request_id=$customerRequestId");

    // 3. File uploads
    if (!empty($_FILES['vehicleFiles']) && isset($_FILES['vehicleFiles']['name']) && is_array($_FILES['vehicleFiles']['name'])) {
        $allowedTypes = ['application/pdf','image/jpeg','image/png','image/jpg'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $uploadBase = __DIR__ . '/../../uploads/vehicle/' . $vehicleRequestId . '/';
        if (!is_dir($uploadBase)) mkdir($uploadBase, 0755, true);

        for ($i = 0; $i < count($_FILES['vehicleFiles']['name']); $i++) {
            if ($_FILES['vehicleFiles']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $_FILES['vehicleFiles']['tmp_name'][$i];
            $size = $_FILES['vehicleFiles']['size'][$i];
            $origName = $_FILES['vehicleFiles']['name'][$i];
            $mime = mime_content_type($tmp) ?: $_FILES['vehicleFiles']['type'][$i];
            if ($size > $maxSize) continue;
            if (!in_array($mime, $allowedTypes)) continue;
            $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($origName));
            $dest = $uploadBase . $safeName;
            if (move_uploaded_file($tmp, $dest)) {
                $stmt2 = $pdo->prepare("
                    INSERT INTO attachments (parent_type, parent_id, file_name, file_path, mime_type, file_size)
                    VALUES ('vehicle', :parent_id, :file_name, :file_path, :mime_type, :file_size)
                ");
                $stmt2->execute([
                    ':parent_id' => $vehicleRequestId,
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
        $subject = 'Vehicle Request Received';
        $body = "
            <h2>Thank you for your request</h2>
            <p>Your vehicle request #{$customerRequestId} has been submitted successfully and is now <strong>Pending</strong>.</p>
            <p>You will receive updates as we process it.</p>
            <p><a href='{$customerLink}'>View your request in the dashboard</a></p>
        ";
        send_customer_email($email, $fullName, $subject, $body);

        // Admin notification
        $adminSubject = 'New Vehicle Request Submitted';
        $adminBody = "
            <h2>New Vehicle Request</h2>
            <p>A new vehicle request has been submitted.</p>
            <ul>
                <li><strong>Request ID:</strong> #{$customerRequestId}</li>
                <li><strong>Customer:</strong> {$fullName} ({$email})</li>
                <li><strong>Plate:</strong> {$plate}</li>
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

    // Determine redirect destination...
    $redirectUrl = base_url('dashboard.php'); // default
    if (isset($_POST['source']) && $_POST['source'] === 'dashboard') {
        $redirectUrl = base_url('vehicle-requests.php');
    }

    if ($isAjax) {
        json_response(true, 'Vehicle request submitted successfully.', $redirectUrl);
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Vehicle request submitted successfully.'];
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