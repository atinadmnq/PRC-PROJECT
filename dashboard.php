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

// Get user's full name
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) $_SESSION['full_name'] = $user['full_name'];
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit(); }

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
    <title>Dashboard</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="dashboard.css" rel="stylesheet">
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
            <small class="text-light">Administrator</small>
        </div>
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <li class="nav-item"><button class="nav-link active" data-section="dashboard"><i class="fas fa-tachometer-alt"></i>Dashboard</button></li>
                <li class="nav-item"><button class="nav-link" data-section="users" style="position: relative;"><i class="fas fa-users"></i>Users<?php if (count($pending_requests) > 0): ?><span class="notification-badge"><?php echo count($pending_requests); ?></span><?php endif; ?></button></li>
                <li class="nav-item"><button class="nav-link" data-section="register"><i class="fas fa-user-plus"></i>Register User</button></li>
                <li class="nav-item"><button class="nav-link" data-section="activity"><i class="fas fa-history"></i>Activity Log</button></li>
                <li class="nav-item"><a href="release.php" class="nav-link"><i class="fas fa-plus-circle"></i>Add Release</a></li>
                <li class="nav-item"><a href="upload.php" class="nav-link"><i class="fas fa-upload"></i>Upload Files</a></li>
                <li class="nav-item"><a href="addprofession.php" class="nav-link"><i class="fas fa-plus-square"></i>Add Profession</a></li>
                <li class="nav-item"><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="main-content">
        <?php if (isset($_SESSION['admin_action']) && $_SESSION['admin_action']): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['admin_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['admin_action']); unset($_SESSION['admin_message']); ?>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section active">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-tachometer-alt me-3"></i>Dashboard Overview</h1>
                <p class="text-muted">Welcome to RILIS</p>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-3 mb-3"><div class="stats-card"><div class="mb-2"><i class="fas fa-file-alt fa-2x"></i></div><h3>0</h3><p class="mb-0">Total Releases</p></div></div>
                <div class="col-md-3 mb-3"><div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"><div class="mb-2"><i class="fas fa-clock fa-2x"></i></div><h3>0</h3><p class="mb-0">Files Uploaded</p></div></div>
                <div class="col-md-3 mb-3"><div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"><div class="mb-2"><i class="fas fa-user-clock fa-2x"></i></div><h3><?php echo count($pending_requests); ?></h3><p class="mb-0">Pending Requests</p></div></div>
                <div class="col-md-3 mb-3"><div class="stats-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;"><div class="mb-2"><i class="fas fa-users fa-2x"></i></div><h3>5</h3><p class="mb-0">Active Staff</p></div></div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card dashboard-card">
                        <div class="card-header bg-transparent"><h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5></div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($activity_logs)): ?>
                                    <?php foreach(array_slice($activity_logs, 0, 5) as $log): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div><i class="fas fa-<?php echo ($log['action'] ?? '') == 'login' ? 'sign-in-alt' : (($log['action'] ?? '') == 'logout' ? 'sign-out-alt' : 'info-circle'); ?> text-<?php echo ($log['action'] ?? '') == 'login' ? 'success' : (($log['action'] ?? '') == 'logout' ? 'danger' : 'primary'); ?> me-2"></i><?php echo htmlspecialchars($log['description'] ?? 'No description available'); ?></div>
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
                        <div class="card-header bg-transparent"><h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Actions</h5></div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="release.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create New Release</a>
                                <a href="upload.php" class="btn btn-outline-primary"><i class="fas fa-upload me-2"></i>Upload Files</a>
                                <button class="btn btn-outline-secondary"><i class="fas fa-download me-2"></i>Export Data</button>
                                <button class="btn btn-warning" data-section="users"><i class="fas fa-user-check me-2"></i>Review Requests (<?php echo count($pending_requests); ?>)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Section -->
        <div id="users" class="content-section">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-users me-3"></i>Staff Permission Requests</h1>
                <p class="text-muted">Review and approve staff requests for releases and file uploads</p>
            </div>

            <?php if (empty($pending_requests)): ?>
                <div class="card dashboard-card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Pending Requests</h5>
                        <p class="text-muted">All staff requests have been processed.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary active" data-filter="all">All Requests (<?php echo count($pending_requests); ?>)</button>
                            <button type="button" class="btn btn-outline-success" data-filter="release">Release Requests (<?php echo count(array_filter($pending_requests, function($r) { return ($r['action'] ?? '') === 'release'; })); ?>)</button>
                            <button type="button" class="btn btn-outline-info" data-filter="upload">Upload Requests (<?php echo count(array_filter($pending_requests, function($r) { return ($r['action'] ?? '') === 'upload'; })); ?>)</button>
                        </div>
                    </div>
                </div>

                <div class="requests-container">
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="request-card" data-type="<?php echo $request['action'] ?? ''; ?>">
                            <div class="request-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="badge <?php echo ($request['action'] ?? '') === 'release' ? 'bg-primary' : 'bg-info'; ?> me-2">
                                                <i class="fas fa-<?php echo ($request['action'] ?? '') === 'release' ? 'unlock-alt' : 'upload'; ?> me-1"></i>
                                                <?php echo ucfirst($request['action'] ?? 'Unknown'); ?>
                                            </span>
                                        </div>
                                        <h5 class="mb-1"><?php echo ucfirst($request['action'] ?? 'Unknown'); ?> Request</h5>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($request['email'] ?? 'Unknown'); ?>
                                            <i class="fas fa-clock ms-3 me-1"></i><?php echo isset($request['created_at']) ? date('M j, Y g:i A', strtotime($request['created_at'])) : 'Unknown time'; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-flex action-buttons justify-content-end">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id'] ?? ''; ?>">
                                                <button type="submit" name="approve_request" class="btn btn-sm btn-approve me-1"><i class="fas fa-check"></i>Approve</button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id'] ?? ''; ?>">
                                                <button type="submit" name="reject_request" class="btn btn-sm btn-reject"><i class="fas fa-times"></i>Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="mb-3"><strong>Reason:</strong> <?php echo htmlspecialchars($request['reason'] ?? 'No reason provided'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Register Section -->
        <div id="register" class="content-section">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-user-plus me-3"></i>Register New User</h1>
        <p class="text-muted">Create new admin or staff accounts</p>
    </div>
    <?php if (isset($_SESSION['reg_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['reg_error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['reg_error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['reg_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['reg_success']; ?>
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
                            <label for="full_name" class="form-label"><i class="fas fa-user me-2"></i>Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <div class="form-text">
                                <small id="passwordHelp">Must contain uppercase, lowercase, number and special character (min 8 chars)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="role" class="form-label"><i class="fas fa-user-tag me-2"></i>User Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="staff">Staff Member</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" name="register_user" class="btn btn-primary"><i class="fas fa-user-plus me-2"></i>Register User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('password');
    const passwordHelp = document.getElementById('passwordHelp');
    
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
});
</script>

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

        // Request filtering
        document.querySelectorAll('[data-filter]').forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.request-card').forEach(card => {
                    card.style.display = (filter === 'all' || card.getAttribute('data-type') === filter) ? 'block' : 'none';
                });
            });
        });

        // Activity log filtering
        document.getElementById('activityFilter').addEventListener('change', function() {
            const filter = this.value;
            document.querySelectorAll('.activity-row').forEach(row => {
                row.style.display = (filter === 'all' || row.getAttribute('data-action') === filter) ? '' : 'none';
            });
        });

        // Form confirmations
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const isApprove = this.querySelector('[name="approve_request"]');
                const isReject = this.querySelector('[name="reject_request"]');
                const requestId = this.querySelector('[name="request_id"]');
                if (requestId) {
                    const id = requestId.value;
                    if (isApprove && !confirm(`Are you sure you want to APPROVE request #${id}?`)) e.preventDefault();
                    else if (isReject && !confirm(`Are you sure you want to REJECT request #${id}?`)) e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>