<!-- staff_dashboard.php -->
<?php
session_start();
require_once 'activity_logger.php'; 

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

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
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

// ROR count
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

// Function to fetch recent activities (limit 3)
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

// ✅ Add this to assign recent activities for display on dashboard
$recentActivities = getRecentActivities($pdo, 3);

// RTS count
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
    <!-- Include sidebar styles -->
    <link href="css/Staff_dashboard.css" rel="stylesheet">
    <style>
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
    <!-- Include sidebar -->
    <?php include 'staff_panel.php'; ?>
    
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
                            <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="staff_viewData.php" class="btn btn-outline-info"><i class="fas fa-table me-2"></i>View ROR Data</a>
                                <a href="staff_rts_view.php" class="btn btn-outline-info"><i class="fas fa-table me-2"></i>View RTS Data</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>