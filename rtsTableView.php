<?php
session_start();
require_once 'activity_logger.php';

// Database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_release_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Get account name
$accountName = $_SESSION['account_name'] ?? $_SESSION['email'] ?? $_SESSION['full_name'] ?? 'Unknown User';

// Get user's full name if not already set
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['full_name'] = $user['full_name'];
    }
}

// Handle individual release
if (isset($_POST['release_id'])) {
    $release_id = intval($_POST['release_id']);

    try {
        $stmt = $pdo->prepare("SELECT name, examination, exam_date, status, upload_timestamp FROM rts_data_onhold WHERE id = ?");
        $stmt->execute([$release_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            $stmt = $pdo->prepare("DELETE FROM rts_data_onhold WHERE id = ?");
            if ($stmt->execute([$release_id])) {
                $description = "Released RTS record: Name = {$record['name']}, Exam = {$record['examination']}, Date = {$record['exam_date']}, Status = {$record['status']}, Uploaded = {$record['upload_timestamp']}";
                logActivity(
                    $pdo,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['full_name'] ?? $accountName,
                    'Release RTS',
                    $description,
                    $record['name'],
                    '',
                    $release_id
                );

                $_SESSION['release_message'] = "Results successfully released for {$record['name']}";
                $_SESSION['release_status'] = 'success';
            } else {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['full_name'] ?? $accountName,
                    'release_failed',
                    "Failed to release RTS record for {$record['name']} - Release ID: {$release_id} - Database Error"
                );

                $_SESSION['release_message'] = "Error releasing record for {$record['name']}";
                $_SESSION['release_status'] = 'error';
            }
        }
    } catch (Exception $e) {
        logActivity(
            $pdo,
            $_SESSION['user_id'] ?? null,
            $_SESSION['full_name'] ?? $accountName,
            'release_exception',
            "Exception during RTS release for Release ID: {$release_id} - Error: " . $e->getMessage()
        );

        $_SESSION['release_message'] = "Release failed due to an error: " . $e->getMessage();
        $_SESSION['release_status'] = 'error';
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?examination=" . urlencode($_GET['examination'] ?? '') . "&search_name=" . urlencode($_GET['search_name'] ?? ''));
    exit();
}

// Fetch examination list
$sql = "SELECT DISTINCT examination FROM rts_data_onhold ORDER BY upload_timestamp DESC";
$result = $pdo->query($sql);
$examinations = $result ? $result->fetchAll(PDO::FETCH_COLUMN) : [];

// Fetch examination counts
$sql_counts = "SELECT examination, COUNT(*) as count FROM rts_data_onhold GROUP BY examination";
$result_counts = $pdo->query($sql_counts);
$exam_counts = [];
$total_count = 0;
if ($result_counts) {
    foreach ($result_counts->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $exam_counts[$row['examination']] = $row['count'];
        $total_count += $row['count'];
    }
}

// Fetch data based on filters
$exam = isset($_GET['examination']) ? trim($_GET['examination']) : '';
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$data = [];

if ($exam !== '') {
    $sql_data = "SELECT id, name, examination, exam_date, upload_timestamp, status 
                 FROM rts_data_onhold 
                 WHERE LOWER(examination) = LOWER(?)";
    $params = [$exam];

    if ($search_name !== '') {
        $sql_data .= " AND name LIKE ?";
        $params[] = "%$search_name%";
    }

    $sql_data .= " ORDER BY upload_timestamp DESC";

    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Uploaded RTS Lists</title>
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

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            font-family: "Century Gothic";
            font-size: 14px;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-1px);
        }

        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table th {
            background: linear-gradient(135deg,rgb(26, 43, 121) 0%,rgb(42, 96, 184) 100%);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
            font-family: "Century Gothic";
        }

        .table td {
            padding: 15px;
            border-color: #e9ecef;
            font-family: "Century Gothic";
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
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

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                margin-right: 0;
            }
            
            .right-panel {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_panel.php'; ?>
    
    <!-- Right Side Panel for Summary -->
    <div class="right-panel">
        <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Summary of RTS Data</h5>
        
        <div class="stat-card text-center">
            <div class="stat-value text-primary"><?= $total_count ?></div>
            <div class="stat-label">Total Records (All Examinations)</div>
        </div>
        
        <h6 class="mb-3 mt-4"><i class="fas fa-list me-2"></i>Records by Examination</h6>
        <?php if (!empty($exam_counts)): ?>
            <?php foreach ($exam_counts as $exam_name => $count): ?>
                <div class="exam-count-item">
                    <div class="exam-count-name"><?= htmlspecialchars($exam_name) ?></div>
                    <div class="exam-count-badge"><?= $count ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-muted text-center py-3">
                <i class="fas fa-info-circle me-1"></i>No RTS data available
            </div>
        <?php endif; ?>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-table me-3"></i>View Uploaded RTS Data</h1>
            <p class="text-muted">Manage and review uploaded RTS examination data</p>
        </div>

        <!-- Filter Card -->
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

        <!-- Data Table -->
        <?php if (!empty($data)): ?>
            <div class="dashboard-card">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>RTS Data for: <?= htmlspecialchars($exam) ?>
                        <span class="badge bg-primary ms-2"><?= count($data) ?> records</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                    <th><i class="fas fa-user me-1"></i>Name</th>
                                    <th><i class="fas fa-graduation-cap me-1"></i>Examination</th>
                                    <th><i class="fas fa-calendar me-1"></i>Exam Date</th>
                                    <th><i class="fas fa-clock me-1"></i>Upload Timestamp</th>
                                    <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                    <th><i class="fas fa-cog me-1"></i>Action</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['id']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= htmlspecialchars($row['examination']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['exam_date']) ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?= htmlspecialchars(date("M d, Y", strtotime($row['upload_timestamp']))) ?><br>
                                                <?= htmlspecialchars(date("h:i A", strtotime($row['upload_timestamp']))) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?= htmlspecialchars($row['status']) ?></span>
                                        </td>
                                        <td>
                                            <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to release (delete) this record?');">
                                                <input type="hidden" name="release_id" value="<?= htmlspecialchars($row['id']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-unlock me-1"></i>Release
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
            <div class="dashboard-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Records Found</h5>
                    <p class="text-muted">No records found for examination: <strong><?= htmlspecialchars($exam) ?></strong></p>
                    <?php if ($search_name !== ''): ?>
                        <p class="text-muted">with name containing: <strong><?= htmlspecialchars($search_name) ?></strong></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

