<?php
// admin/get_refunds.php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
if (!$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM refund_requests
        WHERE request_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$requestId]);
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($refunds);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}