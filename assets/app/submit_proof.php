<?php
// assets/app/submit_proof.php
session_start();
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_helper.php';   // <-- ADDED

// Enable error logging for this script
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../storage/logs/php_errors.log');

function flash_redirect($type, $message, $redirect) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $redirect);
    exit;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    } else {
        flash_redirect('error', 'Please login to continue', base_url('index.php'));
    }
    exit;
}
$user = $auth->getCurrentUser();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    } else {
        flash_redirect('error', 'Invalid request method.', base_url('dashboard.php'));
    }
    exit;
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
    } else {
        flash_redirect('error', 'Invalid or missing CSRF token.', base_url('dashboard.php'));
    }
    exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$requestId = (int)($_POST['request_id'] ?? 0);
if (!$invoiceId || !$requestId) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing invoice or request ID.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'Missing invoice or request ID.', $redirect);
    }
    exit;
}

// Verify invoice belongs to this user and request
$stmt = $pdo->prepare("
    SELECT i.id FROM invoices i
    INNER JOIN customer_requests cr ON i.request_id = cr.id
    WHERE i.id = ? AND cr.user_id = ? AND cr.id = ?
");
$stmt->execute([$invoiceId, $user['id'], $requestId]);
if (!$stmt->fetch()) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invoice not found or access denied.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'Invoice not found or access denied.', $redirect);
    }
    exit;
}

// File upload
if (empty($_FILES['proof_file'])) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No file uploaded.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'No file uploaded.', $redirect);
    }
    exit;
}

$file = $_FILES['proof_file'];
$allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
$maxSize = 10 * 1024 * 1024; // 10MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Upload error.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'Upload error.', $redirect);
    }
    exit;
}
if ($file['size'] > $maxSize) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File exceeds 10MB limit.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'File exceeds 10MB limit.', $redirect);
    }
    exit;
}
$mime = mime_content_type($file['tmp_name']) ?: $file['type'];
if (!in_array($mime, $allowed)) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Only PDF, JPG, PNG allowed.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'Only PDF, JPG, PNG allowed.', $redirect);
    }
    exit;
}

// Save file
$uploadDir = __DIR__ . '/../../uploads/proofs/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$safeName = time() . '_' . bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9\-\.]/', '_', basename($file['name']));
$dest = $uploadDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to save file.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'Failed to save file.', $redirect);
    }
    exit;
}

// Insert payment proof record
$stmt = $pdo->prepare("
    INSERT INTO payment_proofs (invoice_id, user_id, file_name, file_path, mime_type, file_size, status)
    VALUES (?, ?, ?, ?, ?, ?, 'pending')
");
$stmt->execute([$invoiceId, $user['id'], $file['name'], $dest, $mime, $file['size']]);
$proofId = $pdo->lastInsertId();

// Optionally update invoice status to 'sent' if it's draft
$pdo->prepare("UPDATE invoices SET status = 'sent' WHERE id = ? AND status = 'draft'")->execute([$invoiceId]);

// ---------- SEND ADMIN NOTIFICATION (with detailed logging) ----------
try {
    // Fetch customer details for the request
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.email
        FROM users u
        JOIN customer_requests cr ON cr.user_id = u.id
        WHERE cr.id = ?
    ");
    $stmt->execute([$requestId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        $adminSubject = 'New Payment Proof Uploaded';
        $adminBody = "
            <h2>Payment Proof Submitted</h2>
            <p>A customer has uploaded a payment proof for request <strong>#{$requestId}</strong>.</p>
            <ul>
                <li><strong>Customer:</strong> " . htmlspecialchars($customer['full_name']) . " (" . htmlspecialchars($customer['email']) . ")</li>
                <li><strong>Invoice ID:</strong> {$invoiceId}</li>
                <li><strong>Proof ID:</strong> {$proofId}</li>
                <li><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</li>
            </ul>
            <p><a href='" . base_url("admin/dashboard.php") . "'>Go to admin dashboard</a></p>
        ";
        $emailSent = notify_admins($adminSubject, $adminBody);
        if (!$emailSent) {
            error_log("Admin notification function returned false for proof upload (request #$requestId)");
        }
    } else {
        error_log("Could not fetch customer details for request #$requestId – admin notification not sent");
    }
} catch (Exception $e) {
    error_log("Exception in admin notification for proof upload (request #$requestId): " . $e->getMessage());
}

$redirect = base_url("request-details.php?id=$requestId");
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Proof uploaded successfully.', 'redirect' => $redirect]);
} else {
    flash_redirect('success', 'Proof uploaded successfully.', $redirect);
}
exit;