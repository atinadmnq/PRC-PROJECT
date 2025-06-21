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
    // Log logout activity
    logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'logout', 'User logged out successfully');
    session_destroy();
    header("Location: index.php");
    exit();
}

// Function to log activities (updated to remove IP address)
function logActivity($pdo, $userId, $userName, $action, $description, $additionalData = []) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (account_name, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $userName,
            $action,
            $description
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Fetch activity logs with enhanced query (removed IP address)
try {
    $activity_logs = $pdo->query("
        SELECT
            al.*,
            COALESCE(al.account_name, 'Unknown User') as full_name,
            CASE 
                WHEN al.activity_type = 'login' THEN 'success'
                WHEN al.activity_type = 'logout' THEN 'danger'
                WHEN al.activity_type = 'upload_ror' THEN 'info'
                WHEN al.activity_type = 'upload_rts' THEN 'warning'
                WHEN al.activity_type = 'release' THEN 'primary'
                WHEN al.activity_type = 'create' THEN 'primary'
                WHEN al.activity_type = 'update' THEN 'warning'
                WHEN al.activity_type = 'delete' THEN 'danger'
                ELSE 'secondary'
            END as badge_class,
            CASE 
                WHEN al.activity_type = 'login' THEN 'sign-in-alt'
                WHEN al.activity_type = 'logout' THEN 'sign-out-alt'
                WHEN al.activity_type = 'upload_ror' THEN 'file-upload'
                WHEN al.activity_type = 'upload_rts' THEN 'file-upload'
                WHEN al.activity_type = 'release' THEN 'paper-plane'
                WHEN al.activity_type = 'create' THEN 'plus'
                WHEN al.activity_type = 'update' THEN 'edit'
                WHEN al.activity_type = 'delete' THEN 'trash'
                ELSE 'info-circle'
            END as icon_class
        FROM activity_log al
        ORDER BY al.created_at DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity_logs = [];
    error_log("Activity log query failed: " . $e->getMessage());
}

if (isset($_GET['test'])) {
    testActivityLogging($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/dashboard.css" rel="stylesheet">
    <link href="css/staff_dashboard.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            font-family: "Century Gothic";
            margin: 0;
            padding: 0;
        }
        
        .activity-description {
            max-width: 400px;
            word-wrap: break-word;
        }
        
        .badge-activity {
            font-size: 0.85em;
            padding: 0.4em 0.8em;
        }
        
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
     <?php include 'admin_panel.php'; ?>
     <?php include 'staff_panel.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Activity Log Section -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-history me-3"></i>Activity Log
            </h1>
            <p class="text-muted">Monitor all system activities and user actions</p>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label for="activityFilter" class="form-label">Filter by Action:</label>
                    <select class="form-select" id="activityFilter">
                        <option value="all">All Activities</option>
                        <option value="login">Login Activities</option>
                        <option value="logout">Logout Activities</option>
                        <option value="upload_ror">ROR Uploads</option>
                        <option value="upload_rts">RTS Uploads</option>
                        <option value="release">Releases</option>
                        <option value="create">Create Activities</option>
                        <option value="update">Update Activities</option>
                        <option value="delete">Delete Activities</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="dateFilter" class="form-label">Filter by Date:</label>
                    <input type="date" class="form-control" id="dateFilter">
                </div>
                <div class="col-md-4">
                    <label for="searchFilter" class="form-label">Search:</label>
                    <input type="text" class="form-control" id="searchFilter" placeholder="Search by user or description...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-outline-secondary d-block w-100" onclick="clearFilters()">
                        <i class="fas fa-refresh me-1"></i>Clear
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card dashboard-card">
            <div class="card-header bg-transparent">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Recent Activities
                        </h5>
                        <small class="text-muted">Showing last 50 activities</small>
                    </div>
                    <div class="col-auto">
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="refreshActivityLog()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="exportActivityLog()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
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
                                    <tr class="activity-row" 
                                        data-action="<?php echo htmlspecialchars($log['activity_type'] ?? ''); ?>"
                                        data-date="<?php echo isset($log['created_at']) ? date('Y-m-d', strtotime($log['created_at'])) : ''; ?>"
                                        data-user="<?php echo htmlspecialchars(strtolower($log['full_name'] ?? '')); ?>"
                                        data-description="<?php echo htmlspecialchars(strtolower($log['description'] ?? '')); ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar-sm me-2">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium">
                                                        <?php echo htmlspecialchars($log['full_name'] ?? 'Unknown User'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $log['badge_class'] ?? 'secondary'; ?> badge-activity">
                                                <i class="fas fa-<?php echo $log['icon_class'] ?? 'info-circle'; ?> me-1"></i>
                                                <?php 
                                                $actionDisplay = '';
                                                switch($log['activity_type'] ?? '') {
                                                    case 'login':
                                                        $actionDisplay = 'Login';
                                                        break;
                                                    case 'logout':
                                                        $actionDisplay = 'Logout';
                                                        break;
                                                    case 'upload_ror':
                                                        $actionDisplay = 'ROR Upload';
                                                        break;
                                                    case 'upload_rts':
                                                        $actionDisplay = 'RTS Upload';
                                                        break;
                                                    case 'release':
                                                        $actionDisplay = 'Release';
                                                        break;
                                                    case 'create':
                                                        $actionDisplay = 'Create';
                                                        break;
                                                    case 'update':
                                                        $actionDisplay = 'Update';
                                                        break;
                                                    case 'delete':
                                                        $actionDisplay = 'Delete';
                                                        break;
                                                    default:
                                                        $actionDisplay = ucfirst($log['activity_type'] ?? 'Unknown');
                                                }
                                                echo $actionDisplay;
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="activity-description">
                                                <?php echo htmlspecialchars($log['description'] ?? 'No description available'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-nowrap">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo isset($log['created_at']) ? date('M j, Y', strtotime($log['created_at'])) : 'Unknown date'; ?>
                                                    <br>
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo isset($log['created_at']) ? date('g:i A', strtotime($log['created_at'])) : 'Unknown time'; ?>
                                                </small>
                                            </div>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Activity log filtering functionality
            const activityFilter = document.getElementById('activityFilter');
            const dateFilter = document.getElementById('dateFilter');
            const searchFilter = document.getElementById('searchFilter');
            
            function filterActivities() {
                const actionFilter = activityFilter.value;
                const dateFilterValue = dateFilter.value;
                const searchValue = searchFilter.value.toLowerCase();
                const activityRows = document.querySelectorAll('.activity-row');
                
                activityRows.forEach(row => {
                    const rowAction = row.getAttribute('data-action');
                    const rowDate = row.getAttribute('data-date');
                    const rowUser = row.getAttribute('data-user');
                    const rowDescription = row.getAttribute('data-description');
                    
                    let showRow = true;
                    
                    // Filter by action
                    if (actionFilter !== 'all' && rowAction !== actionFilter) {
                        showRow = false;
                    }
                    
                    // Filter by date
                    if (dateFilterValue && rowDate !== dateFilterValue) {
                        showRow = false;
                    }
                    
                    // Filter by search term
                    if (searchValue && 
                        !rowUser.includes(searchValue) && 
                        !rowDescription.includes(searchValue)) {
                        showRow = false;
                    }
                    
                    row.style.display = showRow ? '' : 'none';
                });
            }
            
            // Add event listeners
            if (activityFilter) {
                activityFilter.addEventListener('change', filterActivities);
            }
            
            if (dateFilter) {
                dateFilter.addEventListener('change', filterActivities);
            }
            
            if (searchFilter) {
                searchFilter.addEventListener('input', filterActivities);
            }
        });
        
        function clearFilters() {
            document.getElementById('activityFilter').value = 'all';
            document.getElementById('dateFilter').value = '';
            document.getElementById('searchFilter').value = '';
            
            // Show all rows
            const activityRows = document.querySelectorAll('.activity-row');
            activityRows.forEach(row => {
                row.style.display = '';
            });
        }
        
        function refreshActivityLog() {
            location.reload();
        }
        
        function exportActivityLog() {
            // Create CSV content (updated to remove IP address)
            let csvContent = "User,Action,Description,Date & Time\n";
            
            const visibleRows = document.querySelectorAll('.activity-row[style=""], .activity-row:not([style])');
            visibleRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const user = cells[0].textContent.trim().replace(/\s+/g, ' ');
                const action = cells[1].textContent.trim().replace(/\s+/g, ' ');
                const description = cells[2].textContent.trim().replace(/\s+/g, ' ');
                const dateTime = cells[3].textContent.trim().replace(/\s+/g, ' ');
                
                csvContent += `"${user}","${action}","${description}","${dateTime}"\n`;
            });
            
            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'activity_log_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>