<?php
session_start();
require_once 'activity_logger.php'; // Include the logging functions

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

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    
    // Prevent deletion of current user
    if ($user_id != $_SESSION['user_id']) {
        try {
            // Get user info before deletion for logging
            $user_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $deleted_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $_SESSION['delete_success'] = "User deleted successfully!";
                
                // Log the activity using activity logger
                logActivity(
                    $pdo,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Admin',
                    'delete',
                    "Deleted user account: " . ($deleted_user['full_name'] ?? 'Unknown') . " (" . ($deleted_user['email'] ?? 'Unknown') . ")"
                );
            } else {
                $_SESSION['delete_error'] = "Failed to delete user.";
            }
        } catch (PDOException $e) {
            $_SESSION['delete_error'] = "Error deleting user: " . $e->getMessage();
            
            // Log failed deletion
            logActivity(
                $pdo,
                $_SESSION['user_id'] ?? null,
                $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Admin',
                'delete',
                "Failed to delete user (ID: $user_id) - Error: " . $e->getMessage()
            );
        }
    } else {
        $_SESSION['delete_error'] = "You cannot delete your own account.";
    }
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
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                
                if ($stmt->execute([$full_name, $email, $hashed_password, $role])) {
                    $_SESSION['reg_success'] = "User registered successfully!";
                    
                    // Log user creation using activity logger
                    logUserCreation(
                        $pdo, 
                        $_SESSION['user_id'] ?? null, 
                        $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Admin', 
                        $full_name, 
                        $email,
                        $role
                    );
                } else {
                    $_SESSION['reg_error'] = "Registration failed. Please try again.";
                    
                    // Log failed registration
                    logActivity(
                        $pdo, 
                        $_SESSION['user_id'] ?? null, 
                        $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Admin', 
                        'create', 
                        "Failed to register user: $full_name ($email) as $role"
                    );
                }
            } catch (PDOException $e) {
                $_SESSION['reg_error'] = "Registration failed: " . $e->getMessage();
                
                // Log failed registration with error details
                logActivity(
                    $pdo, 
                    $_SESSION['user_id'] ?? null, 
                    $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Admin', 
                    'create', 
                    "Failed to register user $full_name ($email) as $role - Error: " . $e->getMessage()
                );
            }
        } else {
            $_SESSION['reg_error'] = "Email already exists.";
            
            // Log failed registration attempt
            logActivity(
                $pdo, 
                $_SESSION['user_id'] ?? null, 
                $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Admin', 
                'create', 
                "Failed to register user $full_name ($email) - Email already exists"
            );
        }
    } else {
        $_SESSION['reg_error'] = "Please fill in all fields.";
    }
}

// Fetch all users
try {
    $users_stmt = $pdo->prepare("SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC");
    $users_stmt->execute();
    $all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_users = [];
    error_log("Users fetch query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REGISTER USER</title>
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
        
        .user-avatar-sm {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }
        
        .btn-delete {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
            border-radius: 0.75rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.5rem;
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
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                <li class="nav-item"><a href="account.php" class="nav-link"><i class="fas fa-user-cog"></i>Account Settings</a></li>
                <li class="nav-item"><a href="register_users.php" class="nav-link"> <i class="fas fa-user-plus"></i>Register User</a></li>
                <li class="nav-item"><a href="activity_log.php" class="nav-link"><i class="fas fa-history"></i>Activity Log</a></li>
                <li class="nav-item"><a href="uploadData_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload ROR Data</a></li>
                <li class="nav-item"><a href="rts_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload RTS Data</a></li>
                <li class="nav-item"><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users-cog me-3"></i>User Management
            </h1>
            <p class="text-muted">Register new users and manage existing accounts</p>
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
        
        <?php if (isset($_SESSION['delete_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['delete_error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['delete_error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['delete_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['delete_success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['delete_success']); ?>
        <?php endif; ?>
        
        <!-- Register New User Section -->
        <div class="card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-plus me-2"></i>Register New User
                </h5>
            </div>
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
        
        <!-- Users List Section -->
        <div class="card">
            <div class="card-header bg-transparent">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>Registered Users
                        </h5>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-primary">Total: <?php echo count($all_users); ?> users</span>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-user me-1"></i>Name</th>
                                <th><i class="fas fa-envelope me-1"></i>Email</th>
                                <th><i class="fas fa-user-tag me-1"></i>Role</th>
                                <th><i class="fas fa-calendar me-1"></i>Registered</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_users)): ?>
                                <?php foreach ($all_users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar-sm me-3">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium">
                                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'info'; ?>">
                                                <i class="fas fa-<?php echo $user['role'] == 'admin' ? 'crown' : 'user'; ?> me-1"></i>
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                <br>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('g:i A', strtotime($user['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-delete" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal" 
                                                        data-user-id="<?php echo $user['id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                    <i class="fas fa-trash-alt me-1"></i>Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-user-shield me-1"></i>Current User
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-users me-2"></i>No users found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user account for <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-warning me-1"></i>
                        This action cannot be undone and will permanently remove the user's access to the system.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i>Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Delete modal functionality
            const deleteModal = document.getElementById('deleteModal');
            const deleteButtons = document.querySelectorAll('.btn-delete');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    
                    document.getElementById('deleteUserId').value = userId;
                    document.getElementById('deleteUserName').textContent = userName;
                });
            });
        });
    </script>
</body>
</html>