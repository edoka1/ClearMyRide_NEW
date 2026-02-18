<?php
// admin/download_proof.php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Unauthorized');
}

$proofId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$proofId) {
    http_response_code(400);
    die('Invalid request');
}

try {
    $stmt = $pdo->prepare("
        SELECT file_path, file_name, mime_type 
        FROM payment_proofs 
        WHERE id = ?
    ");
    $stmt->execute([$proofId]);
    $proof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proof) {
        http_response_code(404);
        die('File not found');
    }

    $filePath = $proof['file_path'];
    $fileName = $proof['file_name'];
    $mimeType = $proof['mime_type'] ?: 'application/octet-stream';

    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File does not exist on server');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
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