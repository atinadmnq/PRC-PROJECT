<?php
session_start();

// Database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_release_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: index.php"); 
    exit(); 
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user's full name
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) $_SESSION['full_name'] = $user['full_name'];
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit(); }

// Handle permission requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_permission'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'];
        $reason = 'Permission requested by staff member'; // Default reason
        
        try {
            $stmt = $pdo->prepare("INSERT INTO permission_requests (user_id, user_name, request_type, reason, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['full_name'] ?? $_SESSION['email'],
                $action,
                $reason
            ]);
            
            $_SESSION['permission_requested'] = true;
            $_SESSION['request_message'] = "Your " . strtoupper($action) . " upload permission request has been submitted successfully!";
            
            // Log the activity
            $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, user_name, action, description, created_at) VALUES (?, ?, 'request', ?, NOW())");
            $log_stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['full_name'] ?? $_SESSION['email'],
                "Requested " . strtoupper($action) . " upload permission"
            ]);
            
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to submit request. Please try again.";
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle registration (if admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $full_name = trim($_POST['full_name']); 
    $email = trim($_POST['email']); 
    $password = $_POST['password']; 
    $role = $_POST['role'];
    
    if (!empty($full_name) && !empty($email) && !empty($password) && !empty($role)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() == 0) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $_SESSION['reg_success'] = $stmt->execute([$full_name, $email, $hashed_password, $role]) ? "User registered successfully!" : "Registration failed. Please try again.";
        } else {
            $_SESSION['reg_error'] = "Email already exists.";
        }
    } else {
        $_SESSION['reg_error'] = "Please fill in all fields.";
    }
}

// Handle approve/reject requests (if admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_request']) || isset($_POST['reject_request']))) {
    $request_id = $_POST['request_id'];
    $action = isset($_POST['approve_request']) ? 'approved' : 'rejected';
    $stmt = $pdo->prepare("UPDATE permission_requests SET status = ? WHERE id = ?");
    $stmt->execute([$action, $request_id]);
    $_SESSION['admin_action'] = true;
    $_SESSION['admin_message'] = "Request #" . $request_id . " has been " . $action . ".";
}

// Fetch data
$pending_requests = $pdo->query("SELECT * FROM permission_requests WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fixed activity logs query - check what columns actually exist in your activity_log table
try {
    // First, let's try with a safer query that handles missing columns
    $activity_logs = $pdo->query("
        SELECT 
            al.*,
            COALESCE(al.user_name, al.account_name, 'Unknown User') as full_name
        FROM activity_log al 
        ORDER BY al.created_at DESC 
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback query in case the above doesn't work
    try {
        $activity_logs = $pdo->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        // Add full_name field if it doesn't exist
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - RILIS</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  
    <style>
        body {
            background: #f8f9fa;
            font-family: "Century Gothic";
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, rgb(134, 65, 244) 0%, rgb(66, 165, 245) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            color: white;
        }
        
        .sidebar-brand:hover {
            color: white;
        }
        
        .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.5rem;
        }
        
        .user-avatar-sm {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(134, 65, 244, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-right: 3px solid white;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 30px;
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .permission-required {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .permission-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }

        .request-button {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            border: none;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(238, 90, 36, 0.4);
        }

        .request-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(238, 90, 36, 0.6);
            color: white;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
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
                <li class="nav-item"><button class="nav-link active" data-section="dashboard"><i class="fas fa-tachometer-alt"></i>Dashboard</button></li>
                <li class="nav-item"><button class="nav-link" data-section="activity"><i class="fas fa-history"></i>Activity Log</button></li>
                <li class="nav-item"><button class="nav-link" data-section="ror-upload"><i class="fas fa-upload"></i>Request ROR Upload</button></li>
                <li class="nav-item"><button class="nav-link" data-section="rts-upload"><i class="fas fa-upload"></i>Request RTS Upload</button></li>
                <li class="nav-item"><a href="staff_rts_view.php" class="nav-link"><i class="fas fa-table"></i>RTS Table View</a></li>
                <li class="nav-item"><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="main-content">
        <?php if (!empty($_SESSION['permission_requested'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['request_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['permission_requested'], $_SESSION['request_message']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section active">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-tachometer-alt me-3"></i>Staff Dashboard</h1>
                <p class="text-muted">Welcome to RILIS Staff Portal</p>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="mb-2"><i class="fas fa-upload fa-2x"></i></div>
                        <h3>ROR Upload</h3>
                        <p class="mb-0">Request Permission</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="mb-2"><i class="fas fa-database fa-2x"></i></div>
                        <h3>RTS Upload</h3>
                        <p class="mb-0">Request Permission</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="mb-2"><i class="fas fa-table fa-2x"></i></div>
                        <h3>View Data</h3>
                        <p class="mb-0">RTS Table</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card dashboard-card">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
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
                                        <small class="text-muted"><?php echo isset($log['created_at']) ? date('M j, g:i A', strtotime($log['created_at'])) : 'Unknown time'; ?></small>
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
                            <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" data-section="ror-upload"><i class="fas fa-upload me-2"></i>Request ROR Upload</button>
                                <button class="btn btn-outline-secondary" data-section="rts-upload"><i class="fas fa-database me-2"></i>Request RTS Upload</button>
                                <a href="staff_rts_view.php" class="btn btn-outline-info"><i class="fas fa-table me-2"></i>View RTS Data</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROR Upload Request Section -->
        <div id="ror-upload" class="content-section">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-upload me-3"></i>Request ROR Upload Permission</h1>
                <p class="text-muted">Click the button below to request ROR file upload access</p>
            </div>
            <div class="permission-required">
                <div class="permission-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="mb-3">Admin Permission Required</h3>
                <p class="mb-4">You need administrator approval to upload ROR files to the system.</p>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="ror">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="request_permission" value="1">
                    <button type="submit" class="request-button me-3">
                        <i class="fas fa-paper-plane me-2"></i>Request Permission
                    </button>
                </form>
                <button type="button" class="btn btn-outline-light" data-section="dashboard">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </button>
            </div>
        </div>

        <!-- RTS Upload Request Section -->
        <div id="rts-upload" class="content-section">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-database me-3"></i>Request RTS Upload Permission</h1>
                <p class="text-muted">Click the button below to request RTS data upload access</p>
            </div>
            <div class="permission-required">
                <div class="permission-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="mb-3">Admin Permission Required</h3>
                <p class="mb-4">You need administrator approval to upload RTS data to the system.</p>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="rts">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="request_permission" value="1">
                    <button type="submit" class="request-button me-3">
                        <i class="fas fa-paper-plane me-2"></i>Request Permission
                    </button>
                </form>
                <button type="button" class="btn btn-outline-light" data-section="dashboard">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </button>
            </div>
        </div>

        <!-- Activity Log Section -->
        <div id="activity" class="content-section">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-history me-3"></i>Activity Log</h1>
                <p class="text-muted">Monitor all system activities and user actions</p>
            </div>

            <div class="card dashboard-card">
                <div class="card-header bg-transparent">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Recent Activities</h5>
                        </div>
                        <div class="col-auto">
                            <select class="form-select form-select-sm" id="activityFilter">
                                <option value="all">All Activities</option>
                                <option value="login">Login Activities</option>
                                <option value="logout">Logout Activities</option>
                                <option value="create">Create Activities</option>
                                <option value="update">Update Activities</option>
                                <option value="delete">Delete Activities</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-user me-1"></i>User</th>
                                    <th><i class="fas fa-cog me-1"></i>Action</th>
                                    <th><i class="fas fa-info-circle me-1"></i>Description</th>
                                    <th><i class="fas fa-clock me-1"></i>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody id="activityTableBody">
                                <?php if (!empty($activity_logs)): ?>
                                    <?php foreach ($activity_logs as $log): ?>
                                    <tr class="activity-row" data-action="<?php echo htmlspecialchars($log['action'] ?? ''); ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar-sm me-2"><i class="fas fa-user"></i></div>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown User'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($log['action'] ?? '') == 'login' ? 'success' : 
                                                    (($log['action'] ?? '') == 'logout' ? 'danger' : 
                                                    (($log['action'] ?? '') == 'create' ? 'primary' : 
                                                    (($log['action'] ?? '') == 'update' ? 'warning' : 
                                                    (($log['action'] ?? '') == 'delete' ? 'danger' : 'secondary')))); 
                                            ?>">
                                                <i class="fas fa-<?php 
                                                    echo ($log['action'] ?? '') == 'login' ? 'sign-in-alt' : 
                                                        (($log['action'] ?? '') == 'logout' ? 'sign-out-alt' : 
                                                        (($log['action'] ?? '') == 'create' ? 'plus' : 
                                                        (($log['action'] ?? '') == 'update' ? 'edit' : 
                                                        (($log['action'] ?? '') == 'delete' ? 'trash' : 'info-circle')))); 
                                                ?> me-1"></i><?php echo ucfirst(htmlspecialchars($log['action'] ?? 'Unknown')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['description'] ?? 'No description available'); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i><?php echo isset($log['created_at']) ? date('M j, Y', strtotime($log['created_at'])) : 'Unknown date'; ?><br>
                                                <i class="fas fa-clock me-1"></i><?php echo isset($log['created_at']) ? date('g:i A', strtotime($log['created_at'])) : 'Unknown time'; ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle me-2"></i>No activity logs available
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation functionality
        document.querySelectorAll('.nav-link[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
                document.getElementById(this.getAttribute('data-section')).classList.add('active');
            });
        });

        document.querySelectorAll('button[data-section]').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                const targetSection = this.getAttribute('data-section');
                document.querySelector(`.nav-link[data-section="${targetSection}"]`).classList.add('active');
                document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
                document.getElementById(targetSection).classList.add('active');
            });
        });

        // Activity log filtering
        document.getElementById('activityFilter').addEventListener('change', function() {
            const filter = this.value;
            document.querySelectorAll('.activity-row').forEach(row => {
                row.style.display = (filter === 'all' || row.getAttribute('data-action') === filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>