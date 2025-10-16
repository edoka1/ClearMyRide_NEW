<?php
// assets/app/admin/logout.php
session_start();
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;
