<?php
// assets/app/admin/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

// simple check for admin session
if (empty($_SESSION['admin_id'])) {
    // redirect to admin login - adjust path relative to this file
    header("Location: ../admin/login.php");
    exit;
}
