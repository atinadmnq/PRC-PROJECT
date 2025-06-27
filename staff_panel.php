<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// ✅ Enforce staff-only access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    // Redirect non-staff users (e.g., admin) to another page or show access denied
    header("Location: unauthorized.php"); // You can change this to admin_dashboard.php if needed
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <a href="#" class="sidebar-brand">
            <img src="img/rilis-logo.png" alt="RILIS" style="height: 35px; margin-right: 3px;">
            RILIS
        </a>
    </div>
    <div class="user-info">
        <div class="user-avatar"><i class="fas fa-user"></i></div>
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User'); ?></div>
        <small class="text-light"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></small>
        <small class="text-light">Staff Member</small>
    </div>
    <nav class="nav-menu">
        <ul class="list-unstyled">
            <li class="nav-item">
                <a href="staff_dashboard.php" class="nav-link <?php echo $current_page == 'staff_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="account.php" class="nav-link">
                    <i class="fas fa-user-cog"></i>Account Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="activity_log.php" class="nav-link <?php echo $current_page == 'activity_log.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>Activity Log
                </a>
            </li>
            <li class="nav-item">
                <a href="staff_rts_view.php" class="nav-link <?php echo $current_page == 'staff_rts_view.php' ? 'active' : ''; ?>">
                    <i class="fas fa-table"></i>RTS Table View
                </a>
            </li>
            <li class="nav-item">
                <a href="staff_viewData.php" class="nav-link <?php echo $current_page == 'staff_viewData.php' ? 'active' : ''; ?>">
                    <i class="fas fa-table"></i>ROR Table View
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php?logout=true" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </li>
        </ul>
    </nav>
</div>
