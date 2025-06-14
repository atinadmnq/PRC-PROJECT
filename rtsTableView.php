<?php
include 'db_connect.php';

// Handle "Release" (Delete) action
if (isset($_POST['release_id'])) {
    $release_id = intval($_POST['release_id']);
    
    $stmt = $conn->prepare("DELETE FROM rts_data_onhold WHERE id = ?");
    $stmt->bind_param("i", $release_id);
    
    if ($stmt->execute()) {
        // Optional: add a message or redirect after successful delete
        header("Location: " . $_SERVER['PHP_SELF'] . "?examination=" . urlencode($_GET['examination'] ?? '') . "&search_name=" . urlencode($_GET['search_name'] ?? ''));
        exit;
    } else {
        echo "Error releasing record: " . $conn->error;
    }
    $stmt->close();
}

// Get all distinct examinations
$sql = "SELECT DISTINCT examination FROM rts_data_onhold ORDER BY upload_timestamp DESC";
$result = $conn->query($sql);

$examinations = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $examinations[] = $row['examination'];
    }
}

// Get count per examination
$sql_counts = "SELECT examination, COUNT(*) as count FROM rts_data_onhold GROUP BY examination";
$result_counts = $conn->query($sql_counts);

$exam_counts = [];
$total_count = 0;

if ($result_counts) {
    while ($row = $result_counts->fetch_assoc()) {
        $exam_counts[$row['examination']] = $row['count'];
        $total_count += $row['count'];
    }
}

// Read GET parameters safely
$exam = isset($_GET['examination']) ? trim($conn->real_escape_string($_GET['examination'])) : '';
$search_name = isset($_GET['search_name']) ? trim($conn->real_escape_string($_GET['search_name'])) : '';

// If examination selected, fetch its data with optional name filter
$data = [];
if ($exam !== '') {
    $sql_data = "SELECT id, name, examination, exam_date, upload_timestamp, status 
                 FROM rts_data_onhold 
                 WHERE LOWER(examination) = LOWER('$exam')";

    if ($search_name !== '') {
        $sql_data .= " AND name LIKE '%$search_name%'";
    }

    $sql_data .= " ORDER BY upload_timestamp DESC";

    $result_data = $conn->query($sql_data);

    if ($result_data) {
        while ($row = $result_data->fetch_assoc()) {
            $data[] = $row;
        }
    }
}
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
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .summary-card {
            background: linear-gradient(135deg,rgb(59, 82, 182) 0%,rgb(75, 120, 162) 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-family: "Century Gothic";
            font-weight: 600;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
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
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .exam-count-name {
            font-weight: 600;
        }

        .exam-count-number {
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 4px 12px;
            font-weight: 700;
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
            <div class="user-name">Administrator</div>
            <small class="text-light">RTS Data Manager</small>
        </div>
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-users"></i>Users</a></li>
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-user-plus"></i>Register User</a></li>
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-history"></i>Activity Log</a></li>
                <li class="nav-item"><a href="uploadData_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload Files</a></li>
                <li class="nav-item"><a href="rts_ui.php" class="nav-link"><i class="fas fa-upload"></i>RTS Data</a></li>
                <li class="nav-item"><a href="#" class="nav-link active"><i class="fas fa-table"></i>View RTS Data</a></li>
                <li class="nav-item"><a href="dashboard.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-table me-3"></i>View Uploaded RTS Data</h1>
            <p class="text-muted">Manage and review uploaded RTS examination data</p>
        </div>

        <!-- Summary Card -->
        <div class="summary-card">
            <h4 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Data Summary</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <h2 class="mb-0"><?= $total_count ?></h2>
                        <p class="mb-0">Total Records</p>
                    </div>
                </div>
                <div class="col-md-8">
                    <h6 class="mb-3">Records by Examination:</h6>
                    <div class="row">
                        <?php foreach ($exam_counts as $exam_name => $count): ?>
                            <div class="col-md-6 mb-2">
                                <div class="exam-count-item">
                                    <span class="exam-count-name"><?= htmlspecialchars($exam_name) ?></span>
                                    <span class="exam-count-number"><?= $count ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
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