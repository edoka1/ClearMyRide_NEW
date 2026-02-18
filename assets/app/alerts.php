<?php
/**
 * Flash message display helper
 * Call show_flash() anywhere you want to display session flash messages
 */

function show_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        
        // Default values with null coalescing – prevents htmlspecialchars(null) deprecation
        $type = htmlspecialchars($flash['type'] ?? 'info');
        $message = htmlspecialchars($flash['message'] ?? '');
        
        // Optional title support
        $title = isset($flash['title']) ? htmlspecialchars($flash['title']) : '';
        
        // Build alert classes
        $alertClass = 'alert alert-dismissible fade show';
        switch ($type) {
            case 'success':
                $alertClass .= ' alert-success';
                break;
            case 'error':
            case 'danger':
                $alertClass .= ' alert-danger';
                break;
            case 'warning':
                $alertClass .= ' alert-warning';
                break;
            case 'info':
            default:
                $alertClass .= ' alert-info';
                break;
        }
        
        echo '<div class="' . $alertClass . '" role="alert">';
        
        if (!empty($title)) {
            echo '<strong>' . $title . '</strong> ';
        }
        
        echo $message;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        // Clear flash after displaying
        unset($_SESSION['flash']);
    }
}

/**
 * Set a flash message
 * @param string $type    success, error, warning, info
 * @param string $message The message text
 * @param string $title   Optional title
 */
function set_flash($type, $message, $title = '') {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'title' => $title
    ];
}