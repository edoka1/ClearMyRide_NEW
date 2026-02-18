<?php
// Ensure session is started (should be already, but safe)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../assets/app/Auth.php';
$auth = new Auth();
$isLoggedIn = $auth->isLoggedIn();
$user = $isLoggedIn ? $auth->getCurrentUser() : null;
?>
<header class="nav" role="banner" >
    <div class="container">
        <a class="brand" href="/">
            <img src="assets/images/CMR Transparent BG.png" alt="ClearMyRide logo"  >
            <span class="brand-text">ClearMyRide</span>
        </a>

        <div class="nav-actions" role="navigation" aria-label="Primary">
            <?php if ($isLoggedIn): ?>
                <!-- Simple logout button – no redundant dropdown -->
                <a href="assets/app/logout.php" class="btn btn-logout" onclick="return confirm('Logout?')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke-width="2"/>
                        <polyline points="16 17 21 12 16 7" stroke-width="2"/>
                        <line x1="21" y1="12" x2="9" y2="12" stroke-width="2"/>
                    </svg>
                    <span>Logout</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-login">Login</a>
                <a href="#about" class="btn btn-outline">Learn More</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<style>
/* Sharp, clean nav styling – no rounded corners */
.nav {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: white;
    border-bottom: 1px solid #edf2f7;
    z-index: 1030;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
   
}
.nav .container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1440px;
    margin: 0 auto;
    padding: 0 24px;
}
.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #1a202c;
    height: 70px;
}

.brand-text {
    font-size: 1.25rem;
    font-weight: 700;
    color: #0A57FF;
}
.nav-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}
.btn-logout {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0.5rem 1.25rem;
    background: transparent;
    border: 1px solid #e53e3e;
    color: #e53e3e;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.2s;
    border-radius: 0;
}
.btn-logout:hover {
    background: #e53e3e;
    color: white;
}
.btn-logout svg {
    width: 18px;
    height: 18px;
}
.btn-login, .btn-outline {
    padding: 0.5rem 1.25rem;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    border-radius: 0;
}
.btn-login {
    background: #0A57FF;
    border: 1px solid #0A57FF;
    color: white;
}
.btn-login:hover {
    background: #0845cc;
}
.btn-outline {
    border: 1px solid #0A57FF;
    color: #0A57FF;
    background: transparent;
}
.btn-outline:hover {
    background: #0A57FF;
    color: white;
}
/* Ensure body padding-top matches nav height */
body {
    padding-top: 80px;
}
</style>