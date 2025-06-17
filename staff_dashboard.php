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
    $action = $_POST['action']; // 'upload' for ROR or 'rts_upload' for RTS
    $reason = trim($_POST['reason']);
    $user_email = $_SESSION['email'] ?? '';
    
    if (!empty($reason) && !empty($action)) {
        // Check if user already has a pending request for this action
        $stmt = $pdo->prepare("SELECT id FROM permission_requests WHERE email = ? AND action = ? AND status = 'pending'");
        $stmt->execute([$user_email, $action]);
        
        if ($stmt->rowCount() == 0) {
            // Insert new permission request
            $stmt = $pdo->prepare("INSERT INTO permission_requests (email, action, reason, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            if ($stmt->execute([$user_email, $action, $reason])) {
                $_SESSION['permission_requested'] = true;
                $_SESSION['request_message'] = "Permission request submitted successfully! Admin will review your request.";
            } else {
                $_SESSION['error_message'] = "Failed to submit request. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "You already have a pending request for this action.";
        }
    } else {
        $_SESSION['error_message'] = "Please provide a reason for your request.";
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $full_name = trim($_POST['full_name']); $email = trim($_POST['email']); $password = $_POST['password']; $role = $_POST['role'];
    
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

// Handle approve/reject requests
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

// Fixed activity logs query
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

// Function to check user permissions 
function hasPermission($pdo, $user_email, $permission_type) {
    $stmt = $pdo->prepare("
        SELECT id FROM user_permissions 
        WHERE user_email = ? AND permission_type = ? AND status = 'active'
    ");
    $stmt->execute([$user_email, $permission_type]);
    return $stmt->rowCount() > 0;
}
// 4. Function to get user's permissions (for display purposes)
function getUserPermissions($pdo, $user_email) {
    $stmt = $pdo->prepare("
        SELECT permission_type, granted_at, granted_by 
        FROM user_permissions 
        WHERE user_email = ? AND status = 'active'
    ");
    $stmt->execute([$user_email]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link href="css/staff_dashboard.css" rel="stylesheet">
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
                <p class="text-muted">Submit a request to admin for ROR file upload permission</p>
            </div>
            
            <div class="card dashboard-card">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="upload">
                        <div class="mb-3">
                            <label for="ror_reason" class="form-label"><i class="fas fa-comment me-2"></i>Reason for Request</label>
                            <textarea class="form-control" id="ror_reason" name="reason" rows="4" placeholder="Please explain why you need ROR upload permission..." required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="request_permission" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-section="dashboard">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- RTS Upload Request Section -->
        <div id="rts-upload" class="content-section">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-database me-3"></i>Request RTS Upload Permission</h1>
                <p class="text-muted">Submit a request to admin for RTS data upload permission</p>
            </div>
            
            <div class="card dashboard-card">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="rts_upload">
                        <div class="mb-3">
                            <label for="rts_reason" class="form-label"><i class="fas fa-comment me-2"></i>Reason for Request</label>
                            <textarea class="form-control" id="rts_reason" name="reason" rows="4" placeholder="Please explain why you need RTS upload permission..." required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="request_permission" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-section="dashboard">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </button>
                        </div>
                    </form>
                </div>
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
                                    <tr class="activity-row" data-action="<?php echo $log['action'] ?? ''; ?>">
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
                                                ?> me-1"></i><?php echo ucfirst($log['action'] ?? 'Unknown'); ?>
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
    <script src="staff_dashboard.js"></script>
</body>
</html>