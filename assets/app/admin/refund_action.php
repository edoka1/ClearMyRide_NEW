<?php
// admin/refund_action.php
ob_start();
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/status_functions.php';
require_once __DIR__ . '/../mail_helper.php';

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

$refundId = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$adminNote = trim($_POST['admin_note'] ?? '');

if (!$refundId || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT rr.*, cr.status as request_status, cr.user_id as customer_id
        FROM refund_requests rr
        JOIN customer_requests cr ON rr.request_id = cr.id
        WHERE rr.id = ?
    ");
    $stmt->execute([$refundId]);
    $refund = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$refund) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Refund request not found']);
        exit;
    }

    if ($action === 'approve') {
        $proofFilePath = null;
        if (isset($_FILES['refund_proof']) && $_FILES['refund_proof']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['refund_proof'];
            $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 10 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                throw new Exception('File exceeds 10MB limit.');
            }
            $mime = mime_content_type($file['tmp_name']) ?: $file['type'];
            if (!in_array($mime, $allowed)) {
                throw new Exception('Only PDF, JPG, PNG allowed.');
            }

            $uploadDir = __DIR__ . '/../../uploads/refund_proofs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $safeName = time() . '_' . bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9\-\.]/', '_', basename($file['name']));
            $dest = $uploadDir . $safeName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $proofFilePath = $dest;
            } else {
                throw new Exception('Failed to upload proof file.');
            }
        } else {
            throw new Exception('Refund proof file is required for approval.');
        }

        $stmt = $pdo->prepare("
            UPDATE refund_requests
            SET status = 'approved', admin_id = ?, admin_note = ?, refund_proof_file = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['admin_id'], $adminNote, $proofFilePath, $refundId]);

        $customerEmailStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $customerEmailStmt->execute([$refund['customer_id']]);
        $customerEmail = $customerEmailStmt->fetchColumn();
        if ($customerEmail) {
            $subject = 'Refund Approved';
            $body = "Your refund request #{$refundId} has been approved. We will process it shortly. <a href='" . base_url("dashboard.php") . "'>View your request</a>";
            send_customer_email($customerEmail, '', $subject, $body);
        }

        $message = 'Refund approved. Customer will confirm receipt.';
    } else {
        if (empty($adminNote)) {
            throw new Exception('Rejection reason is required.');
        }

        $stmt = $pdo->prepare("
            UPDATE refund_requests
            SET status = 'rejected', admin_id = ?, admin_note = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['admin_id'], $adminNote, $refundId]);

        if ($refund['initiated_by'] === 'customer' && $refund['request_status'] === 'Refund Requested') {
            $trans = transitionStatus(
                $pdo,
                $refund['request_id'],
                'Processing',
                'admin',
                $_SESSION['admin_id'],
                'Refund rejected: ' . $adminNote
            );
            if (!$trans['success']) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $trans['message']]);
                exit;
            }
        }

        $customerEmailStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $customerEmailStmt->execute([$refund['customer_id']]);
        $customerEmail = $customerEmailStmt->fetchColumn();
        if ($customerEmail) {
            $subject = 'Refund Request Rejected';
            $body = "Your refund request #{$refundId} has been rejected. Reason: " . htmlspecialchars($adminNote) . "<br><a href='" . base_url("dashboard.php") . "'>View details</a>";
            send_customer_email($customerEmail, '', $subject, $body);
        }

        $message = 'Refund rejected.';
    }

    $pdo->commit();
    ob_clean();
    echo json_encode(['success' => true, 'message' => $message]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Refund action exception: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}