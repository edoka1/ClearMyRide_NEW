<?php
// assets/app/upload_additional.php
session_start();
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_helper.php';

// Helper to set flash and redirect (for non-AJAX)
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

$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$documentRequestId = isset($_POST['document_request_id']) ? (int)$_POST['document_request_id'] : null;
$description = trim($_POST['doc_description'] ?? '');

if (!$requestId) {
    $redirect = base_url('dashboard.php');
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing request ID.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'Missing request ID.', $redirect);
    }
    exit;
}

// Verify the request belongs to this user
$stmt = $pdo->prepare("
    SELECT id FROM customer_requests 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$requestId, $userId]);
if (!$stmt->fetch()) {
    $redirect = base_url('dashboard.php');
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Request not found or access denied.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'Request not found or access denied.', $redirect);
    }
    exit;
}

// If a document_request_id was provided, verify it belongs to this request
if ($documentRequestId) {
    $stmt = $pdo->prepare("
        SELECT id FROM additional_document_requests 
        WHERE id = ? AND request_id = ?
    ");
    $stmt->execute([$documentRequestId, $requestId]);
    if (!$stmt->fetch()) {
        $redirect = base_url("request-details.php?id=$requestId");
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document request.', 'redirect' => $redirect]);
        } else {
            flash_redirect('error', 'Invalid document request.', $redirect);
        }
        exit;
    }
}

// File upload
if (empty($_FILES['additional_file'])) {
    $redirect = base_url("request-details.php?id=$requestId");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No file uploaded.', 'redirect' => $redirect]);
    } else {
        flash_redirect('error', 'No file uploaded.', $redirect);
    }
    exit;
}

$file = $_FILES['additional_file'];
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
$uploadDir = __DIR__ . '/../../uploads/additional/';
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

// Insert into additional_documents (with optional document_request_id)
$stmt = $pdo->prepare("
    INSERT INTO additional_documents 
    (request_id, user_id, document_request_id, file_name, file_path, mime_type, file_size, description)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $requestId,
    $userId,
    $documentRequestId,
    $file['name'],
    $dest,
    $mime,
    $file['size'],
    $description ?: null
]);

// If this upload was for a specific document request, update its status to 'fulfilled'
if ($documentRequestId) {
    $pdo->prepare("
        UPDATE additional_document_requests 
        SET status = 'fulfilled' 
        WHERE id = ? AND status = 'pending'
    ")->execute([$documentRequestId]);
}

// Also update the main request's updated_at timestamp (optional)
$pdo->prepare("UPDATE customer_requests SET updated_at = NOW() WHERE id = ?")->execute([$requestId]);

// Also update the main request's updated_at timestamp (optional)
$pdo->prepare("UPDATE customer_requests SET updated_at = NOW() WHERE id = ?")->execute([$requestId]);

// --- START: Admin notification for document upload ---
   $adminLink = base_url("admin/dashboard.php");
$subject = 'Additional Document Uploaded by Customer';
$body = "
    <h2>Document Upload Notification</h2>
    <p>A customer has uploaded an additional document for request <strong>#{$requestId}</strong>.</p>
    <ul>
        <li><strong>File:</strong> " . htmlspecialchars($file['name']) . "</li>
        <li><strong>Description:</strong> " . htmlspecialchars($description ?: '—') . "</li>
        " . ($documentRequestId ? "<li><strong>In response to document request #{$documentRequestId}</strong></li>" : "") . "
    </ul>
    <p><a href='{$adminLink}'>View in admin dashboard</a></p>
";
$altBody = "Customer uploaded a document for request #{$requestId}. File: {$file['name']}. View at {$adminLink}";
notify_admins($subject, $body, $altBody);
// --- END: Admin notification ---


$redirect = base_url("request-details.php?id=$requestId");
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Document uploaded successfully.', 'redirect' => $redirect]);
} else {
    flash_redirect('success', 'Document uploaded successfully.', $redirect);
}
exit;