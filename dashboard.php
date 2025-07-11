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

// Function to get recent activities - FIXED VERSION
function getRecentActivities($pdo, $limit = 3) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                account_name,
                activity_type,
                description,
                created_at
            FROM activity_log 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = [];
        foreach ($activities as $activity) {
            $summary[] = [
                'summary' => $activity['account_name'] . " - " . $activity['description'] . " on " . date('M j, Y g:i A', strtotime($activity['created_at'])),
                'action' => $activity['activity_type'] ?? 'unknown',
                'created_at' => $activity['created_at']
            ];
        }

        return $summary;
    } catch (PDOException $e) {
        error_log("Recent activities query failed: " . $e->getMessage());
        return [];
    }
}

// Initialize recentActivities with empty array as fallback
$recentActivities = [];

// Get recent activities for dashboard display
try {
    $recentActivities = getRecentActivities($pdo, 3);
} catch (Exception $e) {
    error_log("Error getting recent activities: " . $e->getMessage());
    $recentActivities = []; // Ensure it's always an array
}

// Fetch activity logs for main display
$activity_logs = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            account_name,
            activity_type,
            description,
            created_at,
            ip_address
        FROM activity_log 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add compatibility fields
    foreach ($activity_logs as &$log) {
        $log['full_name'] = $log['account_name'] ?? 'Unknown User';
        $log['action'] = $log['activity_type'] ?? 'unknown';
    }
} catch (PDOException $e) {
    error_log("Activity log query failed: " . $e->getMessage());
    $activity_logs = [];
}

// Get ROR count
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

// Get RTS count
$rts_count = 0;
try {
    $sql_rts_count = "SELECT COUNT(*) as total_rts FROM rts_data_onhold";
    $result_rts = $pdo->query($sql_rts_count);
    if ($result_rts) {
        $row_rts = $result_rts->fetch(PDO::FETCH_ASSOC);
        $rts_count = $row_rts['total_rts'];
    }
} catch (PDOException $e) {
    error_log("RTS count query failed: " . $e->getMessage());
    $rts_count = 0;
}

// Debug output (remove this after testing)
echo "<!-- DEBUG: Recent Activities Count: " . count($recentActivities) . " -->";
if (!empty($recentActivities)) {
    echo "<!-- DEBUG: First Activity: " . htmlspecialchars(print_r($recentActivities[0], true)) . " -->";
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
    <?php include 'admin_panel.php'; ?>
    
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
                    <div class="stats-card" style="background:linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);">
                        <div class="mb-2">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                        <h3><?php echo number_format($rts_count); ?></h3>
                        <p class="mb-0 fonty">Total RTS</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);">
                        <div class="mb-2">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <h3><?php echo number_format($ror_count); ?></h3>
                        <p class="mb-0 fonty">Total ROR</p>
                    </div>
                </div>
                <!-- Add more stats cards if needed -->
                <div class="col-md-3 mb-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);">
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
                    <!-- Enhanced Recent Activities Section -->
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock me-2"></i>Recent Activities
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentActivities)): ?>
                                <div class="activity-timeline">
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="activity-item" data-action="<?php echo htmlspecialchars($activity['action'] ?? ''); ?>">
                                            <div class="activity-icon">
                                                <i class="fas fa-<?php 
                                                    $action = $activity['action'] ?? '';
                                                    echo $action === 'login' ? 'sign-in-alt' : 
                                                         ($action === 'logout' ? 'sign-out-alt' : 
                                                         ($action === 'upload_ror' ? 'file-upload' : 
                                                         ($action === 'upload_rts' ? 'cloud-upload-alt' : 'info-circle'))); 
                                                ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                           <div class="activity-text">
                                                    <?php echo htmlspecialchars($activity['summary']); ?>
                                                </div>
                                                <div class="activity-time">
                                                    <?php echo isset($activity['created_at']) ? date('M j, Y g:i A', strtotime($activity['created_at'])) : 'Unknown time'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="activity_log.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-list me-1"></i>View All Activities
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                                    <p class="mb-0">No recent activities to display</p>
                                </div>
                            <?php endif; ?>
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
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard.js">
    </script>
</body>
</html>