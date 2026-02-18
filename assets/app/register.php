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
$confirm_password = $_POST['confirm_password'] ?? '';
$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email']);
    exit;
}

if (empty($full_name)) {
    echo json_encode(['success' => false, 'message' => 'Please provide your full name']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain both letters and numbers']);
    exit;
}

if ($password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

$auth = new Auth();
$result = $auth->register($email, $password, $full_name, $phone);

if ($result['success']) {
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful'
    ]);
} else {
    echo json_encode($result);
}
exit;