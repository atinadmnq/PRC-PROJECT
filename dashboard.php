<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_release_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Get user's full name if not set
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['full_name'] = $user['full_name'];
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle user registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    if (!empty($full_name) && !empty($email) && !empty($password) && !empty($role)) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() == 0) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$full_name, $email, $hashed_password, $role])) {
                $_SESSION['reg_success'] = "User registered successfully!";
            } else {
                $_SESSION['reg_error'] = "Registration failed. Please try again.";
            }
        } else {
            $_SESSION['reg_error'] = "Email already exists.";
        }
    } else {
        $_SESSION['reg_error'] = "Please fill in all fields.";
    }
}

// Fetch activity logs
try {
    $activity_logs = $pdo->query("
        SELECT
            al.*,
            COALESCE(al.user_name, al.account_name, 'Unknown User') as full_name
        FROM activity_log al
        ORDER BY al.created_at DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $activity_logs = $pdo->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($activity_logs as &$log) {
            if (!isset($log['full_name'])) {
                $log['full_name'] = $log['user_name'] ?? $log['account_name'] ?? 'Unknown User';
            }
        }
    } catch (PDOException $e2) {
        $activity_logs = [];
        error_log("Activity log query failed: " . $e2->getMessage());
    }
}

$ror_count = 0;
try {
    $sql_ror_count = "SELECT COUNT(*) as total_ror FROM roravailable";
    $result_ror = $pdo->query($sql_ror_count);
    if ($result_ror) {
        $row_ror = $result_ror->fetch(PDO::FETCH_ASSOC);
        $ror_count = $row_ror['total_ror'];
    }
} catch (PDOException $e) {
    error_log("ROR count query failed: " . $e->getMessage());
    $ror_count = 0;
}

// Optional: Get RTS count if you have an RTS table
$rts_count = 0;
try {
    // Assuming you have an RTS table - replace 'rts_table_name' with your actual RTS table name
    $sql_rts_count = "SELECT COUNT(*) as total_rts FROM rts_data_onhold";
    $result_rts = $pdo->query($sql_rts_count);
    if ($result_rts) {
        $row_rts = $result_rts->fetch(PDO::FETCH_ASSOC);
        $rts_count = $row_rts['total_rts'];
    }
} catch (PDOException $e) {
    // Table might not exist or query failed
    error_log("RTS count query failed: " . $e->getMessage());
    $rts_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/dashboard.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: "Century Gothic";
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
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
                <li class="nav-item"><button class="nav-link" data-section="dashboard"><i class="fas fa-tachometer-alt"></i>Dashboard</button></li>
                <li class="nav-item"><a href="account.php" class="nav-link"><i class="fas fa-user-cog"></i>Account Settings</a></li>
                <li class="nav-item"><a href="register_users.php" class="nav-link"><i class="fas fa-user-plus"></i>Register User </a></li>
                <li class="nav-item"><a href="activity_log.php" class="nav-link"><i class="fas fa-history"></i>Activity Log</a></li>
                <li class="nav-item"><a href="uploadData_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload ROR Data</a></li>
                <li class="nav-item"> <a href="rts_ui.php" class="nav-link"> <i class="fas fa-upload"></i>Upload RTS Data</a></li>
                <li class="nav-item"><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
               
        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section active">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-tachometer-alt me-3" id="fonty"></i>Dashboard Overview
                </h1>
                <p class="text-muted">Report of Rating Issuance Logistics and Inventory System</p>
            </div>
            
            <div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="mb-2">
                <i class="fas fa-file-alt fa-2x"></i>
            </div>
            <h3><?php echo number_format($rts_count); ?></h3>
            <p class="mb-0 fonty">Total RTS</p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="mb-2">
                <i class="fas fa-clock fa-2x"></i>
            </div>
            <h3><?php echo number_format($ror_count); ?></h3>
            <p class="mb-0 fonty">Total ROR</p>
        </div>
    </div>
    <!-- Add more stats cards if needed -->
    <div class="col-md-3 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="mb-2">
                <i class="fas fa-users fa-2x"></i>
            </div>
            <h3><?php echo number_format($ror_count + $rts_count); ?></h3>
            <p class="mb-0 fonty">Total Records</p>
        </div>
    </div>
</div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card dashboard-card">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history me-2"></i>Recent Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($activity_logs)): ?>
                                    <?php foreach(array_slice($activity_logs, 0, 5) as $log): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-<?php echo ($log['action'] ?? '') == 'login' ? 'sign-in-alt' : (($log['action'] ?? '') == 'logout' ? 'sign-out-alt' : 'info-circle'); ?> text-<?php echo ($log['action'] ?? '') == 'login' ? 'success' : (($log['action'] ?? '') == 'logout' ? 'danger' : 'primary'); ?> me-2"></i>
                                                <?php echo htmlspecialchars($log['description'] ?? 'No description available'); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo isset($log['created_at']) ? date('M j, g:i A', strtotime($log['created_at'])) : 'Unknown time'; ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>No recent activities to display
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="uploadData_ui.php" class="btn btn-outline-primary">
                                    <i class="fas fa-upload me-2"></i>Upload ROR
                                </a>
                                <a href="rts_ui.php" class="btn btn-outline-primary">
                                    <i class="fas fa-upload me-2"></i>Upload RTS
                                </a>
                                <a href="" class="btn btn-outline-secondary">
                                    <i class="fas fa-download me-2"></i>Export Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Register User Section -->
        <div id="register" class="content-section">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-user-plus me-3"></i>Register New User
                </h1>
                <p class="text-muted">Create new admin or staff accounts</p>
            </div>
            
            <!-- Registration Messages -->
            <?php if (isset($_SESSION['reg_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['reg_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['reg_error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['reg_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['reg_success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['reg_success']); ?>
            <?php endif; ?>
            
            <div class="card dashboard-card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Full Name
                                    </label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email Address
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                    <div class="form-text">
                                        <small id="passwordHelp">Must contain uppercase, lowercase, number and special character (min 8 chars)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">
                                        <i class="fas fa-user-tag me-2"></i>User Role
                                    </label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin">Administrator</option>
                                        <option value="staff">Staff Member</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="register_user" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Register User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation functionality
            const navLinks = document.querySelectorAll('.nav-link[data-section]');
            const contentSections = document.querySelectorAll('.content-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const targetSection = this.getAttribute('data-section');
                    
                    // Remove active class from all nav links and content sections
                    navLinks.forEach(nav => nav.classList.remove('active'));
                    contentSections.forEach(section => section.classList.remove('active'));
                    
                    // Add active class to clicked nav link and corresponding content section
                    this.classList.add('active');
                    document.getElementById(targetSection).classList.add('active');
                });
            });
            
            // Password validation
            const passwordField = document.getElementById('password');
            const passwordHelp = document.getElementById('passwordHelp');
            
            if (passwordField && passwordHelp) {
                passwordField.addEventListener('input', function() {
                    const password = this.value;
                    const hasUpper = /[A-Z]/.test(password);
                    const hasLower = /[a-z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                    const isLongEnough = password.length >= 8;
                    
                    if (hasUpper && hasLower && hasNumber && hasSpecial && isLongEnough) {
                        passwordHelp.className = 'text-success';
                        passwordHelp.textContent = 'Strong password ✓';
                    } else {
                        passwordHelp.className = 'text-danger';
                        passwordHelp.textContent = 'Must contain uppercase, lowercase, number and special character (min 8 chars)';
                    }
                });
            }
            
            // Activity log filtering functionality
            const activityFilter = document.getElementById('activityFilter');
            if (activityFilter) {
                activityFilter.addEventListener('change', function() {
                    const filter = this.value;
                    const activityRows = document.querySelectorAll('.activity-row');
                    
                    activityRows.forEach(row => {
                        const rowAction = row.getAttribute('data-action');
                        
                        // Show row if filter is 'all' or matches the row's action
                        if (filter === 'all' || rowAction === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>