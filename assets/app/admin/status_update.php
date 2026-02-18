<?php
// admin/status_update.php
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

$requestId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if (!$requestId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$actionMap = [
    'under_review'    => 'Under Review',
    'awaiting_payment' => 'Awaiting Payment',
    'process'         => 'Processing',
    'complete'        => 'Completed',
    'cancel'          => 'Cancelled',
    'complete_with_refund' => 'Completed',
];

if (!isset($actionMap[$action])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$newStatus = $actionMap[$action];
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

try {
    $pdo->beginTransaction();

    if ($action === 'cancel' && !empty($note)) {
        $stmt = $pdo->prepare("UPDATE customer_requests SET cancellation_reason = ? WHERE id = ?");
        $stmt->execute([$note, $requestId]);
    }

    if ($action === 'complete_with_refund') {
        $refundAmount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
        $refundReason = isset($_POST['refund_reason']) ? trim($_POST['refund_reason']) : '';

        if ($refundAmount > 0) {
            $stmt = $pdo->prepare("SELECT user_id FROM customer_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $customerId = $stmt->fetchColumn();

            if (!$customerId) {
                throw new Exception('Customer not found');
            }

            $stmt = $pdo->prepare("
                INSERT INTO refund_requests 
                (request_id, customer_id, initiated_by, admin_id, amount, reason, status)
                VALUES (?, ?, 'admin', ?, ?, ?, 'pending')
            ");
            $stmt->execute([$requestId, $customerId, $_SESSION['admin_id'], $refundAmount, $refundReason]);
        }
    }

    $result = transitionStatus(
        $pdo,
        $requestId,
        $newStatus,
        'admin',
        $_SESSION['admin_id'],
        $note ?: "Action: $action"
    );

    if (!$result['success']) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode($result);
        exit;
    }

    $pdo->commit();
    ob_clean();
    echo json_encode($result);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Status update exception: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'An error occurred.']);
    exit;
}