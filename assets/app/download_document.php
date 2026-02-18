<?php
// assets/app/download_document.php
session_start();
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    die('Unauthorized');
}
$user = $auth->getCurrentUser();
$userId = $user['id'];

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (!$type || !$id) {
    http_response_code(400);
    die('Invalid request');
}

try {
    $filePath = null;
    $fileName = null;
    $mimeType = null;

    switch ($type) {
        case 'additional_document':
            $stmt = $pdo->prepare("
                SELECT file_path, file_name, mime_type, request_id
                FROM additional_documents
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc) {
                // Verify the document belongs to the current user
                $stmt2 = $pdo->prepare("SELECT user_id FROM customer_requests WHERE id = ?");
                $stmt2->execute([$doc['request_id']]);
                $ownerId = $stmt2->fetchColumn();
                if ($ownerId != $userId) {
                    http_response_code(403);
                    die('Access denied');
                }
                $filePath = $doc['file_path'];
                $fileName = $doc['file_name'];
                $mimeType = $doc['mime_type'];
            }
            break;

        case 'payment_proof':
            $stmt = $pdo->prepare("
                SELECT pp.file_path, pp.file_name, pp.mime_type, i.request_id
                FROM payment_proofs pp
                JOIN invoices i ON pp.invoice_id = i.id
                WHERE pp.id = ?
            ");
            $stmt->execute([$id]);
            $proof = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($proof) {
                $stmt2 = $pdo->prepare("SELECT user_id FROM customer_requests WHERE id = ?");
                $stmt2->execute([$proof['request_id']]);
                $ownerId = $stmt2->fetchColumn();
                if ($ownerId != $userId) {
                    http_response_code(403);
                    die('Access denied');
                }
                $filePath = $proof['file_path'];
                $fileName = $proof['file_name'];
                $mimeType = $proof['mime_type'];
            }
            break;

        case 'refund_proof':
            $stmt = $pdo->prepare("
                SELECT refund_proof_file, id, request_id
                FROM refund_requests
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$id, $userId]);
            $refund = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($refund && !empty($refund['refund_proof_file'])) {
                $filePath = $refund['refund_proof_file'];
                $fileName = basename($refund['refund_proof_file']);
                $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
            }
            break;
    }

    if (!$filePath || !file_exists($filePath)) {
        http_response_code(404);
        die('File not found');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    die('Server error');
}