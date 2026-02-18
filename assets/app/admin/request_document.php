<?php
// admin/request_document.php
ob_start();
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/status_functions.php';

ob_clean();
header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno] $errstr in $errfile line $errline");
    return false;
});

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$requestType = isset($_POST['request_type']) ? trim($_POST['request_type']) : '';
$message = trim($_POST['message'] ?? '');

if (!$requestId || !$requestType || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Request ID, type and message are required']);
    exit;
}

if (!in_array($requestType, ['vehicle', 'license'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request type']);
    exit;
}

try {
    $pdo->beginTransaction();

    $customerRequestId = $requestId;

    $stmt = $pdo->prepare("SELECT id FROM customer_requests WHERE id = ?");
    $stmt->execute([$customerRequestId]);
    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer request not found.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO additional_document_requests (request_id, admin_id, message, status, requested_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$customerRequestId, $_SESSION['admin_id'], $message]);

    $title = "Additional Documents Requested";
    $description = "Admin requested additional documents: " . $message;
    $stmt2 = $pdo->prepare("
        INSERT INTO request_updates (request_id, user_id, update_type, title, description, created_at)
        VALUES (?, ?, 'admin_note', ?, ?, NOW())
    ");
    $stmt2->execute([$customerRequestId, $_SESSION['admin_id'], $title, $description]);

    $stmt = $pdo->prepare("SELECT status FROM customer_requests WHERE id = ?");
    $stmt->execute([$customerRequestId]);
    $currentStatus = $stmt->fetchColumn();
    notifyStatusChange($pdo, $customerRequestId, $currentStatus, $currentStatus, 'admin', [
        'document_message' => $message
    ]);

    $pdo->commit();
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Document request sent']);
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Document request PDO error: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Document request exception: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}