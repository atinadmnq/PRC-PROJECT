<!-- uploadData_ui.php -->
<?php
session_start();

include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: index.php"); 
    exit(); 
}

// Get user's full name if not already in session
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user) $_SESSION['full_name'] = $user['full_name'];
    $stmt->close();
}

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
    <link href="css/uploadData_ui.css" rel="stylesheet">
    
    
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
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-users"></i>Users</a></li>
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-user-plus"></i>Register User</a></li>
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-history"></i>Activity Log</a></li>
                <li class="nav-item"><a href="uploadData_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload ROR Data</a></li>
                <li class="nav-item"><a href="rts_ui.php" class="nav-link"><i class="fas fa-upload"></i>Upload RTS Data</a></li>
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
            <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Data Summary</h4>
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h2 class="mb-1"><?= $total_count ?></h2>
                        <p class="mb-0">Total Records</p>
                    </div>
                </div>
                <div class="col-md-9">
                    <h6 class="mb-3">Records by Examination:</h6>
                    <?php if (!empty($exam_counts)): ?>
                        <?php foreach ($exam_counts as $exam_name => $count): ?>
                            <div class="exam-count-item">
                                <span class="exam-count-name"><?= htmlspecialchars($exam_name) ?></span>
                                <span class="exam-count-number"><?= $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-light mb-0"><i class="fas fa-info-circle me-1"></i>No examination data available</p>
                    <?php endif; ?>
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