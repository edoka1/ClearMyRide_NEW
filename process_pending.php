<?php
session_start();
require_once __DIR__ . '/assets/app/Auth.php';

$auth = new Auth();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: /');
    exit;
}

// Check if there's pending form data in localStorage redirect
// This is now just a backup - JavaScript handles the main flow
if (!empty($_SESSION['pending_form_data'])) {
    // Process old session-based pending data (for backwards compatibility)
    $pending_data = $_SESSION['pending_form_data'];
    unset($_SESSION['pending_form_data']);
    
    // Determine redirect based on form type
    $redirect = 'dashboard.php';
    if (isset($pending_data['type'])) {
        $redirect = 'index.php#form';
    }
    
    header('Location: ' . $redirect);
    exit;
} else {
    // If no pending data, just go to dashboard
    header('Location: dashboard.php');
    exit;
}