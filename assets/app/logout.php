<?php
session_start();
require_once __DIR__ . '/Auth.php';

$auth = new Auth();
$auth->logout();

// Clear localStorage for pending form data
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging out...</title>
    <script>
        // Clear localStorage before redirecting
        localStorage.removeItem('pendingFormData');
        // Redirect to index page
        window.location.href = '../../index.php';
    </script>
</head>
<body>
    <p>Logging out...</p>
</body>
</html>
<?php exit; ?>