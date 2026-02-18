<?php
session_start();
require_once __DIR__ . '/Auth.php';

header('Content-Type: application/json');

// Validate CSRF
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate input
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please provide valid email and password']);
    exit;
}

$auth = new Auth();
$result = $auth->login($email, $password);

if ($result['success']) {
    echo json_encode([
        'success' => true, 
        'message' => 'Login successful'
    ]);
} else {
    echo json_encode($result);
}
exit;