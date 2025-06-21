<?php
// Make sure session is started before including this file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="#" class="sidebar-brand">
            <img src="img/rilis-logo.png" alt="RILIS" style="height: 35px; margin-right: 3px;">
            RILIS
        </a>
    </div>
    
    <div class="user-info">
        <div class="user-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="user-name">
            <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User'); ?>
        </div>
        <small class="text-light"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></small>
        <small class="text-light">Administrator</small>
    </div>
    
    <nav class="nav-menu">
        <ul class="list-unstyled"> 
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
            <li class="nav-item"><a href="account.php" class="nav-link"><i class="fas fa-user-cog"></i>Account Settings</a></li>
            <li class="nav-item"><a href="register_users.php" class="nav-link"><i class="fas fa-user-plus"></i>Register User</a></li>
            <li class="nav-item"><a href="activity_log.php" class="nav-link"><i class="fas fa-history"></i>Activity Log</a></li>
            <li class="nav-item"><a href="uploadData_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload ROR Data</a></li>
            <li class="nav-item"><a href="rts_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload RTS Data</a></li>
            <li class="nav-item"><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </nav>
</div>
</body>
</html>