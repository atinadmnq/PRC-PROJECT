<!-- staff_viewData.php -->
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

// Check if user is logged in as staff
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Get user's full name if not already set
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) $_SESSION['full_name'] = $user['full_name'];
}

// Include or define the logActivity function
function logActivity($username, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (username, action, details, timestamp) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $action, $details]);
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Log the RTS table view access
logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'rts_table_view_access', 'Staff accessed RTS table view');

// Fixed activity logs query - matching staff_dashboard.php
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

// Handle "Release" (Delete) action
if (isset($_POST['release_id'])) {
    $release_id = intval($_POST['release_id']);
    
    $stmt = $pdo->prepare("DELETE FROM roravailable WHERE id = ?");
    
    if ($stmt->execute([$release_id])) {
        // Optional: add a message or redirect after successful delete
        header("Location: " . $_SERVER['PHP_SELF'] . "?examination=" . urlencode($_GET['examination'] ?? '') . "&search_name=" . urlencode($_GET['search_name'] ?? ''));
        exit;
    } else {
        echo "Error releasing record: " . $stmt->errorInfo()[2];
    }
}

// Get all distinct examinations
$sql = "SELECT DISTINCT examination FROM roravailable ORDER BY upload_timestamp DESC";
$result = $pdo->query($sql);

$examinations = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $examinations[] = $row['examination'];
    }
}

// Get count per examination
$sql_counts = "SELECT examination, COUNT(*) as count FROM roravailable GROUP BY examination";
$result_counts = $pdo->query($sql_counts);

$exam_counts = [];
$total_count = 0;

if ($result_counts) {
    while ($row = $result_counts->fetch(PDO::FETCH_ASSOC)) {
        $exam_counts[$row['examination']] = $row['count'];
        $total_count += $row['count'];
    }
}

// Read GET parameters safely
$exam = isset($_GET['examination']) ? trim($_GET['examination']) : '';
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// If examination selected, fetch its data with optional name filter
$data = [];
if ($exam !== '') {
    $sql_data = "SELECT id, name, examination, exam_date, upload_timestamp, status 
                 FROM roravailable 
                 WHERE LOWER(examination) = LOWER(?)";
    $params = [$exam];

    if ($search_name !== '') {
        $sql_data .= " AND name LIKE ?";
        $params[] = '%' . $search_name . '%';
    }

    $sql_data .= " ORDER BY upload_timestamp DESC";

    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Uploaded ROR Data - RILIS</title>
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
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
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
            margin-right: 320px;
            min-height: 100vh;
            padding: 30px;
        }
        
        .right-panel {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 320px;
            background: white;
            border-left: 1px solid #e9ecef;
            z-index: 999;
            overflow-y: auto;
            padding: 30px 20px;
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
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .exam-count-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .exam-count-name {
            font-weight: 500;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .exam-count-badge {
            background: #4285f4;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Updated Filter Card Styles (from RTS view) */
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-family: "Century Gothic";
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-family: "Century Gothic";
            font-weight: 600;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
   <?php include 'staff_panel.php'; ?>
    <!-- Right Side Panel for Summary -->
    <div class="right-panel">
        <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Summary of Uploaded Data</h5>
        
        <div class="stat-card text-center">
            <div class="stat-value text-primary"><?= $total_count ?></div>
            <div class="stat-label">Total Records (All Examinations)</div>
        </div>
        
        <h6 class="mb-3 mt-4"><i class="fas fa-list me-2"></i>Records by Examination</h6>
        <?php foreach ($exam_counts as $exam_name => $count): ?>
            <div class="exam-count-item">
                <div class="exam-count-name"><?= htmlspecialchars($exam_name) ?></div>
                <div class="exam-count-badge"><?= $count ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="main-content">
        <!-- ROR Data Section -->
        <div id="ror-data" class="content-section active">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-table me-3"></i>View Uploaded ROR Data</h1>
                <p class="text-muted">Report of Rating Issuance Logistics and Inventory System</p>
            </div>

            <!-- Updated Filter Card (copied from RTS view) -->
            <div class="filter-card">
                <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
                <form method="get" action="" id="filterForm">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label for="examSelect" class="form-label"><i class="fas fa-graduation-cap me-1"></i>Select Examination</label>
                            <select name="examination" id="examSelect" class="form-select" required onchange="document.getElementById('filterForm').submit()">
                                <option value="">-- Choose an examination --</option>
                                <?php foreach ($examinations as $examination): ?>
                                    <option value="<?= htmlspecialchars($examination) ?>" <?= ($exam === $examination) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($examination) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="searchName" class="form-label"><i class="fas fa-search me-1"></i>Search Name</label>
                            <input type="text" id="searchName" name="search_name" class="form-control" placeholder="Enter name to search" value="<?= htmlspecialchars($search_name) ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Search</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="d-flex justify-content-end mb-2">
            <label class="me-2">Show 
            <select id="rowsPerPageSelect" class="form-select d-inline-block w-auto">
            <option value="20" selected>20</option>
            <option value="50">50</option>
            <option value="100">100</option>
            </select> entries
            </label>
            </div>

            <!-- Data Table Card -->
            <?php if (!empty($data)): ?>
            <div class="card dashboard-card">
                <div class="card-header bg-transparent">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>ROR Data Records</h5>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-info"><?= count($data) ?> records found</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <tr class="paginated-row">
                                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                    <th><i class="fas fa-user me-1"></i>Name</th>
                                    <th><i class="fas fa-file-alt me-1"></i>Examination</th>
                                    <th><i class="fas fa-calendar me-1"></i>Exam Date</th>
                                    <th><i class="fas fa-upload me-1"></i>Upload Timestamp</th>
                                    <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                    <th><i class="fas fa-cog me-1"></i>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar-sm me-2"><i class="fas fa-user"></i></div>
                                                <span class="fw-medium"><?= htmlspecialchars($row['name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['examination']) ?></td>
                                        <td>
                                            <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                            <?= htmlspecialchars($row['exam_date']) ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= htmlspecialchars(date("M-d-Y H:i:s", strtotime($row['upload_timestamp']))) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" action="" onsubmit="return confirm('Are you sure you want to release (delete) this record?');" class="d-inline">
                                                <input type="hidden" name="release_id" value="<?= htmlspecialchars($row['id']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>Release
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php elseif ($exam !== ''): ?>
            <div class="card dashboard-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No records found</h5>
                    <p class="text-muted">No records found for examination: <strong><?= htmlspecialchars($exam) ?></strong></p>
                </div>
            </div>
            <?php endif; ?>
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
            const navLinks = document.querySelectorAll('.nav-link[data-section]');
            const contentSections = document.querySelectorAll('.content-section');

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetSection = this.getAttribute('data-section');
                    
                    // Remove active class from all nav links
                    navLinks.forEach(nl => nl.classList.remove('active'));
                    
                    // Add active class to clicked nav link
                    this.classList.add('active');
                    
                    // Hide all content sections
                    contentSections.forEach(section => section.classList.remove('active'));
                    
                    // Show target section
                    const targetElement = document.getElementById(targetSection);
                    if (targetElement) {
                        targetElement.classList.add('active');
                    }
                });
            });

            // Activity log filtering functionality - updated to match staff_dashboard.php
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

        
        //Pagination

       document.addEventListener('DOMContentLoaded', function () {
    // Get table body rows (excluding header)
    const tableBody = document.querySelector('tbody');
    if (!tableBody) return; // Exit if no table body found
    
    const rows = tableBody.querySelectorAll('tr');
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    
    // Create pagination controls if they don't exist
    let paginationContainer = document.getElementById('paginationContainer');
    if (!paginationContainer) {
        // Create pagination container
        paginationContainer = document.createElement('div');
        paginationContainer.id = 'paginationContainer';
        paginationContainer.className = 'd-flex justify-content-between align-items-center mt-3';
        paginationContainer.innerHTML = `
            <div id="pageInfo" class="text-muted">Page 1 of 1</div>
            <div id="paginationControls" class="d-flex align-items-center gap-2">
                <button id="prevPage" class="btn btn-outline-secondary btn-sm" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <button id="nextPage" class="btn btn-outline-secondary btn-sm">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;
        
        // Insert pagination container after the table card
        const tableCard = document.querySelector('.table-responsive').closest('.card');
        if (tableCard) {
            tableCard.parentNode.insertBefore(paginationContainer, tableCard.nextSibling);
        }
    }
    
    const pageInfo = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    let currentPage = 1;
    let rowsPerPage = parseInt(rowsPerPageSelect.value);
    let totalPages = Math.ceil(rows.length / rowsPerPage);

    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        rows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        totalPages = Math.ceil(rows.length / rowsPerPage);
        
        // Update page info
        if (pageInfo) {
            if (rows.length === 0) {
                pageInfo.textContent = 'No records to display';
            } else {
                const displayStart = start + 1;
                const displayEnd = Math.min(end, rows.length);
                pageInfo.textContent = `Showing ${displayStart}-${displayEnd} of ${rows.length} entries`;
            }
        }
        
        // Update button states
        if (prevBtn) prevBtn.disabled = page === 1;
        if (nextBtn) nextBtn.disabled = page === totalPages || rows.length === 0;
    }

    // Event listeners
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                showPage(currentPage);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                showPage(currentPage);
            }
        });
    }

    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => {
            rowsPerPage = parseInt(rowsPerPageSelect.value);
            currentPage = 1; // Reset to first page
            totalPages = Math.ceil(rows.length / rowsPerPage);
            showPage(currentPage);
        });
    }

    // Initial setup
    if (rows.length > 0) {
        showPage(currentPage);
        if (paginationContainer) paginationContainer.style.display = 'flex';
    } else {
        if (paginationContainer) paginationContainer.style.display = 'none';
    }

    // Update pagination when filters change (for dynamic content)
    const observer = new MutationObserver(() => {
        const newRows = tableBody.querySelectorAll('tr');
        if (newRows.length !== rows.length) {
            // Rows have changed, reinitialize
            location.reload(); // Simple approach, or you could update the rows variable
        }
    });

    observer.observe(tableBody, { childList: true });
});
    
    </script>
</body>
</html>