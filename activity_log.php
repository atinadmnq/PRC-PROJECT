<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    // Log the logout activity
    logActivity($_SESSION['account_name'] ?? 'Logged out', 'logout', 'User logged out');
    session_destroy();
    header("Location: index.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'prc_release_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Simple Activity logging function
function logActivity($accountName, $activityType, $description, $clientName = null, $fileName = null, $releaseId = null) {
    global $pdo;
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (account_name, activity_type, description, client_name, file_name, release_id, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$accountName, $activityType, $description, $clientName, $fileName, $releaseId, $ipAddress]);
    } catch(PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter settings
$activity_type = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$account_filter = isset($_GET['account_filter']) ? $_GET['account_filter'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$whereConditions = [];
$params = [];

if (!empty($activity_type)) {
    $whereConditions[] = "activity_type = ?";
    $params[] = $activity_type;
}

if (!empty($account_filter)) {
    $whereConditions[] = "account_name LIKE ?";
    $params[] = "%$account_filter%";
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM activity_log $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get activity logs
$sql = "SELECT * FROM activity_log $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get distinct accounts for filter dropdown
$accountStmt = $pdo->prepare("SELECT DISTINCT account_name FROM activity_log ORDER BY account_name");
$accountStmt->execute();
$accounts = $accountStmt->fetchAll(PDO::FETCH_COLUMN);

// Get activity icon and color
function getActivityIcon($type) {
    $icons = [
        'login' => 'fas fa-sign-in-alt text-success',
        'upload' => 'fas fa-upload text-primary',
        'release' => 'fas fa-rocket text-warning'
    ];
    return $icons[$type] ?? 'fas fa-info-circle text-muted';
}

// Get activity badge color
function getActivityBadge($type) {
    $badges = [
        'login' => 'badge bg-success',
        'upload' => 'badge bg-primary',
        'release' => 'badge bg-warning'
    ];
    return $badges[$type] ?? 'badge bg-secondary';
}

// Helper function to format time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31104000) return floor($time/2592000) . ' months ago';
    return floor($time/31104000) . ' years ago';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - PRC Release</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
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
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
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
        
        .page-header {
            margin-bottom: 30px;
            color: white;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        
        .activity-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background-color: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        
        .stats-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-shield-alt me-2"></i>
                PRC Release
            </a>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['account_name'] ?? 'User'); ?></div>
            <small class="text-light">Administrator</small>
        </div>
        
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="release.php" class="nav-link">
                        <i class="fas fa-search"></i>
                        Client Search
                    </a>
                </li>
                <li class="nav-item">
                    <a href="activity_log.php" class="nav-link active">
                        <i class="fas fa-history"></i>
                        Activity Log
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?logout=1" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-history me-3"></i>
                Activity Log
            </h1>
            <p class="text-white-50">Track login, upload, and release activities</p>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php echo $totalRecords; ?></div>
                    <div class="text-muted">Total Activities</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php 
                        $todayStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE DATE(created_at) = CURDATE()");
                        $todayStmt->execute();
                        echo $todayStmt->fetchColumn();
                    ?></div>
                    <div class="text-muted">Today's Activities</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php 
                        $loginStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE activity_type = 'login' AND DATE(created_at) = CURDATE()");
                        $loginStmt->execute();
                        echo $loginStmt->fetchColumn();
                    ?></div>
                    <div class="text-muted">Today's Logins</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-number"><?php 
                        $releaseStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE activity_type = 'release' AND DATE(created_at) = CURDATE()");
                        $releaseStmt->execute();
                        echo $releaseStmt->fetchColumn();
                    ?></div>
                    <div class="text-muted">Today's Releases</div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Activity Type</label>
                            <select class="form-select" name="activity_type">
                                <option value="">All Types</option>
                                <option value="login" <?php echo $activity_type === 'login' ? 'selected' : ''; ?>>Login</option>
                                <option value="upload" <?php echo $activity_type === 'upload' ? 'selected' : ''; ?>>Upload</option>
                                <option value="release" <?php echo $activity_type === 'release' ? 'selected' : ''; ?>>Release</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Account</label>
                            <select class="form-select" name="account_filter">
                                <option value="">All Accounts</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?php echo htmlspecialchars($account); ?>" <?php echo $account_filter === $account ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($account); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="activity_log.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Activity List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activities</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($activities)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No activities found</h5>
                        <p class="text-muted">Try adjusting your filters or check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="d-flex align-items-center">
                                <div class="activity-icon me-3">
                                    <i class="<?php echo getActivityIcon($activity['activity_type']); ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <strong class="me-2"><?php echo htmlspecialchars($activity['account_name']); ?></strong>
                                        <span class="<?php echo getActivityBadge($activity['activity_type']); ?>">
                                            <?php echo ucfirst($activity['activity_type']); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted mb-1">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if ($activity['client_name']): ?>
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($activity['client_name']); ?> |
                                        <?php endif; ?>
                                        <?php if ($activity['file_name']): ?>
                                            <i class="fas fa-file"></i> <?php echo htmlspecialchars($activity['file_name']); ?> |
                                        <?php endif; ?>
                                        <?php if ($activity['release_id']): ?>
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($activity['release_id']); ?> |
                                        <?php endif; ?>
                                        <i class="fas fa-globe"></i> <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted"><?php echo timeAgo($activity['created_at']); ?></div>
                                    <div class="small text-muted"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&activity_type=<?php echo urlencode($activity_type); ?>&account_filter=<?php echo urlencode($account_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&activity_type=<?php echo urlencode($activity_type); ?>&account_filter=<?php echo urlencode($account_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&activity_type=<?php echo urlencode($activity_type); ?>&account_filter=<?php echo urlencode($account_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                Next
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>