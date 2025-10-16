<?php


if (session_status() === PHP_SESSION_NONE) session_start();

function show_flash() {
    if (empty($_SESSION['flash'])) return;
    $flash = $_SESSION['flash'];

    $type_map = [
        'success' => 'success',
        'error'   => 'danger',
        'info'    => 'info',
        'warning' => 'warning'
    ];
    $bs = $type_map[$flash['type']] ?? 'info';
    $msg = htmlspecialchars($flash['message']);
    echo <<<HTML
<div class="container mt-3">
  <div class="alert alert-{$bs} alert-dismissible fade show" role="alert">
    {$msg}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
</div>
HTML;
    unset($_SESSION['flash']);
}
