<?php
// assets/app/download.php
session_start();

// require admin auth
if (empty($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied.";
    exit;
}

require_once __DIR__ . '/db_connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid file id.";
    exit;
}

$stmt = $pdo->prepare("SELECT file_name, file_path, mime_type FROM attachments WHERE id=:id LIMIT 1");
$stmt->execute([':id'=>$id]);
$file = $stmt->fetch();

if (!$file) {
    header("HTTP/1.1 404 Not Found");
    echo "File not found.";
    exit;
}

// Security: ensure the file path is inside the uploads folder
$uploadRoot = realpath(__DIR__ . '/../../uploads');
$real = realpath($file['file_path']);
if ($real === false || strpos($real, $uploadRoot) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied.";
    exit;
}

if (!is_file($real) || !is_readable($real)) {
    header("HTTP/1.1 404 Not Found");
    echo "File not available.";
    exit;
}

$downloadName = basename($file['file_name']);
$mime = $file['mime_type'] ?: 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($real));
header('Cache-Control: no-cache, must-revalidate');
readfile($real);
exit;
