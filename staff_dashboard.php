<!-- staff_dashboard.php -->
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
                <li class="nav-item"><a href="staff_rts_view.php" class="nav-link"><i class="fas fa-table"></i>RTS Table View</a></li>
                <li class="nav-item"><a href="staff_viewData.php" class="nav-link"><i class="fas fa-table"></i>ROR Table View</a></li>
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
                <p class="text-muted">Report of Rating Issuance Logistics and Inventory System</p>
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
                                <a href="staff_rts_view.php" class="btn btn-outline-info"><i class="fas fa-table me-2"></i>View ROR Data</a>
                                <a href="staff_rts_view.php" class="btn btn-outline-info"><i class="fas fa-table me-2"></i>View RTS Data</a>
                            </div>
                        </div>
                    </div>
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
    <script>
        

// Navigation functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle navigation links with data-section attribute
    document.querySelectorAll('.nav-link[data-section]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Hide all content sections
            document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
            
            // Show target section
            const targetSection = this.getAttribute('data-section');
            document.getElementById(targetSection).classList.add('active');
        });
    });

    // Handle buttons with data-section attribute (for quick actions and other buttons)
    document.querySelectorAll('button[data-section]').forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            
            // Add active class to corresponding nav link
            const targetSection = this.getAttribute('data-section');
            const correspondingNavLink = document.querySelector(`.nav-link[data-section="${targetSection}"]`);
            if (correspondingNavLink) {
                correspondingNavLink.classList.add('active');
            }
            
            // Hide all content sections
            document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
            
            // Show target section
            document.getElementById(targetSection).classList.add('active');
        });
    });

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