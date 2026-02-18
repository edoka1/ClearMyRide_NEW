<?php
// assets/app/config.php
if (!function_exists('base_url')) {
    function base_url($path = '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // Get the script directory (e.g. /ClearMyRide_NEW/assets/app)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        // Remove trailing /assets/app to get the project root
        $baseDir = rtrim(preg_replace('#/assets/app$#', '', $scriptDir), '/');
        
        return $protocol . $host . $baseDir . '/' . ltrim($path, '/');
    }
}