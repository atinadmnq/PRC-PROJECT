<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=prc_release_db;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$user_role = $_SESSION['role'] ?? 'staff';
$account_name = $_SESSION['account_name'] ?? 'User';

// Get user's full name if not set
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) $_SESSION['full_name'] = $user['full_name'];
}

// Get all activities - Enhanced query with better data handling
try {
    $activityStmt = $pdo->prepare("
        SELECT 
            al.*,
            COALESCE(al.user_name, al.account_name, u.full_name, 'Unknown User') as staff_name,
            u.email as staff_email
        FROM activity_log al 
        LEFT JOIN users u ON (al.account_name = u.full_name OR al.account_name = u.email)
        ORDER BY al.created_at DESC 
        LIMIT 100
    ");
    $activityStmt->execute();
    $activities = $activityStmt->fetchAll();
} catch (PDOException $e) {
    // Fallback query
    $activityStmt = $pdo->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 100");
    $activityStmt->execute(); 
    $activities = $activityStmt->fetchAll();
    foreach ($activities as &$activity) {
        if (!isset($activity['staff_name'])) {
            $activity['staff_name'] = $activity['account_name'] ?? 'Unknown User';
        }
    }
}

// Get activity statistics for current user
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_activities,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_activities,
        SUM(CASE WHEN activity_type = 'release' AND DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_releases,
        SUM(CASE WHEN activity_type = 'upload' AND DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_uploads
    FROM activity_log 
    WHERE account_name = ?
");
$statsStmt->execute([$account_name]);
$stats = $statsStmt->fetch();

// Get overall statistics
$overallStatsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_all_activities,
        COUNT(DISTINCT account_name) as active_users,
        SUM(CASE WHEN activity_type = 'login' THEN 1 ELSE 0 END) as total_logins,
        SUM(CASE WHEN activity_type = 'upload' THEN 1 ELSE 0 END) as total_uploads,
        SUM(CASE WHEN activity_type = 'release' THEN 1 ELSE 0 END) as total_releases
    FROM activity_log
");
$overallStatsStmt->execute();
$overallStats = $overallStatsStmt->fetch();

function getActivityIcon($type) {
    $icons = [
        'login' => 'fas fa-sign-in-alt', 
        'logout' => 'fas fa-sign-out-alt',
        'upload' => 'fas fa-upload', 
        'release' => 'fas fa-rocket',
        'create' => 'fas fa-plus',
        'update' => 'fas fa-edit',
        'delete' => 'fas fa-trash'
    ];
    return $icons[$type] ?? 'fas fa-info-circle';
}

function getActivityColor($type) {
    $colors = [
        'login' => 'success', 
        'logout' => 'danger',
        'upload' => 'primary', 
        'release' => 'warning',
        'create' => 'info',
        'update' => 'secondary',
        'delete' => 'danger'
    ];
    return $colors[$type] ?? 'secondary';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    return floor($time/86400) . ' days ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - RILIS</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: "Century Gothic", sans-serif;
            margin: 0;
            padding: 0;
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
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .role-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 12px;
            background: #28a745;
            color: white;
        }

        .user-avatar-sm {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $account_name); ?></div>
            <small class="text-light"><?php echo htmlspecialchars($_SESSION['email'] ?? 'staff@prc.com'); ?></small>
            <?php if ($user_role === 'admin'): ?>
                <small class="text-light">Administrator</small>
            <?php else: ?>
                <span class="role-badge">Staff Member</span>
            <?php endif; ?>
        </div>
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                    <li><a href="dashboard.php" class="nav-link"><i class="fas fa-users"></i>Users</a></li>
                    <li><a href="dashboard.php" class="nav-link"><i class="fas fa-user-plus"></i>Register User</a></li>
                    <li><a href="activity_log.php" class="nav-link active"><i class="fas fa-history"></i>Activity Log</a></li>
                    <li><a href="uploadData_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload Files</a></li>
                    <li><a href="rts_ui.php" class="nav-link"><i class="fas fa-upload"></i>RTS Data</a></li>
                    <li><a href="rtsTableView.php" class="nav-link"><i class="fas fa-table"></i>View RTS Data</a></li>
                <?php else: ?>
                    <li><a href="staff_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                    <li><a href="activity_log.php" class="nav-link active"><i class="fas fa-history"></i>Activity Log</a></li>
                    <li><a href="request_ror_upload.php" class="nav-link"><i class="fas fa-upload"></i>Request ROR Upload</a></li>
                    <li><a href="request_rts_upload.php" class="nav-link"><i class="fas fa-upload"></i>Request RTS Upload</a></li>
                    <li><a href="rts_table_view.php" class="nav-link"><i class="fas fa-table"></i>RTS Table View</a></li>
                <?php endif; ?>
                <li><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-history me-3"></i>Activity Log</h1>
            <p class="text-muted">Monitor all system activities and user actions</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="mb-2"><i class="fas fa-chart-line fa-2x"></i></div>
                    <h3><?php echo $overallStats['total_all_activities'] ?? 0; ?></h3>
                    <p class="mb-0">Total Activities</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="mb-2"><i class="fas fa-users fa-2x"></i></div>
                    <h3><?php echo $overallStats['active_users'] ?? 0; ?></h3>
                    <p class="mb-0">Active Users</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="mb-2"><i class="fas fa-upload fa-2x"></i></div>
                    <h3><?php echo $overallStats['total_uploads'] ?? 0; ?></h3>
                    <p class="mb-0">Total Uploads</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                    <div class="mb-2"><i class="fas fa-rocket fa-2x"></i></div>
                    <h3><?php echo $overallStats['total_releases'] ?? 0; ?></h3>
                    <p class="mb-0">Total Releases</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Activities</h5>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-6">
                            <select class="form-select form-select-sm" id="activityTypeFilter">
                                <option value="all">All Types</option>
                                <option value="login">Login Activities</option>
                                <option value="logout">Logout Activities</option>
                                <option value="upload">Upload Activities</option>
                                <option value="release">Release Activities</option>
                                <option value="create">Create Activities</option>
                                <option value="update">Update Activities</option>
                                <option value="delete">Delete Activities</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <select class="form-select form-select-sm" id="dateFilter">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activities Table -->
        <div class="card dashboard-card">
            <div class="card-header bg-transparent">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>All Activities</h5>
                    </div>
                    <div class="col-auto">
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="refreshActivities()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="exportActivities()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($activities)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No activities found</h5>
                        <p class="text-muted">Activities will appear here as users interact with the system</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-user me-1"></i>User</th>
                                    <th><i class="fas fa-cog me-1"></i>Action</th>
                                    <th><i class="fas fa-info-circle me-1"></i>Description</th>
                                    <th><i class="fas fa-building me-1"></i>Client/File</th>
                                    <th><i class="fas fa-clock me-1"></i>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody id="activitiesTableBody">
                                <?php foreach ($activities as $activity): ?>
                                    <tr class="activity-row" 
                                        data-type="<?php echo htmlspecialchars($activity['activity_type'] ?? 'unknown'); ?>"
                                        data-date="<?php echo $activity['created_at'] ? date('Y-m-d', strtotime($activity['created_at'])) : ''; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar-sm me-2">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($activity['staff_name'] ?? 'Unknown User'); ?></div>
                                                    <?php if (!empty($activity['staff_email'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($activity['staff_email']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="activity-icon bg-<?php echo getActivityColor($activity['activity_type'] ?? 'unknown'); ?> bg-opacity-10 text-<?php echo getActivityColor($activity['activity_type'] ?? 'unknown'); ?> me-2">
                                                    <i class="<?php echo getActivityIcon($activity['activity_type'] ?? 'unknown'); ?>"></i>
                                                </div>
                                                <span class="badge bg-<?php echo getActivityColor($activity['activity_type'] ?? 'unknown'); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($activity['activity_type'] ?? 'Unknown')); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($activity['description'] ?? 'No description available'); ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($activity['client_name']) || !empty($activity['file_name'])): ?>
                                                <div class="small">
                                                    <?php if (!empty($activity['client_name'])): ?>
                                                        <div><i class="fas fa-building text-primary me-1"></i><?php echo htmlspecialchars($activity['client_name']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($activity['file_name'])): ?>
                                                        <div><i class="fas fa-file text-info me-1"></i><?php echo htmlspecialchars($activity['file_name']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo $activity['created_at'] ? date('M d, Y', strtotime($activity['created_at'])) : 'Unknown date'; ?></div>
                                            <div class="small text-muted"><?php echo $activity['created_at'] ? date('h:i A', strtotime($activity['created_at'])) : 'Unknown time'; ?></div>
                                            <div class="small text-success"><?php echo $activity['created_at'] ? timeAgo($activity['created_at']) : 'Unknown time'; ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activity type filtering
        document.getElementById('activityTypeFilter').addEventListener('change', function() {
            const filter = this.value;
            const rows = document.querySelectorAll('.activity-row');
            
            rows.forEach(row => {
                if (filter === 'all' || row.dataset.type === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Date filtering
        document.getElementById('dateFilter').addEventListener('change', function() {
            const filter = this.value;
            const rows = document.querySelectorAll('.activity-row');
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            
            rows.forEach(row => {
                const rowDate = row.dataset.date;
                let show = true;
                
                if (filter === 'today') {
                    show = rowDate === todayStr;
                } else if (filter === 'week') {
                    const weekAgo = new Date(today);
                    weekAgo.setDate(today.getDate() - 7);
                    const weekAgoStr = weekAgo.toISOString().split('T')[0];
                    show = rowDate >= weekAgoStr;
                } else if (filter === 'month') {
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(today.getMonth() - 1);
                    const monthAgoStr = monthAgo.toISOString().split('T')[0];
                    show = rowDate >= monthAgoStr;
                }
                
                row.style.display = show ? '' : 'none';
            });
        });

        // Refresh activities
        function refreshActivities() {
            location.reload();
        }

        // Export activities (placeholder)
        function exportActivities() {
            alert('Export functionality would be implemented here');
        }

        // Mobile menu toggle (if needed)
        function toggleMobileMenu() {
            document.querySelector('.sidebar').classList.toggle('mobile-open');
        }
    </script>
</body>
</html>