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

// Enhanced "Release" (Delete) action with proper logging
if (isset($_POST['release_id'])) {
    $release_id = intval($_POST['release_id']);
    $examinee_name = $_POST['examinee_name'] ?? 'Unknown Examinee';
    $examination = $_POST['examination'] ?? 'Unknown Examination';
    
    try {
        $stmt = $pdo->prepare("SELECT name, examination, exam_date, status, upload_timestamp FROM roravailable WHERE id = ?");
        $stmt->execute([$release_id]);
        $examinee_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($examinee_data) {
            $examinee_name = $examinee_data['name'];
            $examination = $examinee_data['examination'];
            
            $stmt = $pdo->prepare("DELETE FROM roravailable WHERE id = ?");
            
            if ($stmt->execute([$release_id])) {
                logReleaseActivity(
                    $pdo,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['full_name'] ?? $accountName,
                    $examinee_name,
                    'individual',
                    "Released ROR record: Name = {$examinee_data['name']}, Exam = {$examinee_data['examination']}, Date = {$examinee_data['exam_date']}, Status = {$examinee_data['status']}, Uploaded = {$examinee_data['upload_timestamp']}"
                );
                
                $_SESSION['release_message'] = "Results successfully released for {$examinee_name}";
                $_SESSION['release_status'] = 'success';
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?examination=" . urlencode($_GET['examination'] ?? '') . "&search_name=" . urlencode($_GET['search_name'] ?? ''));
                exit;
            } else {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['full_name'] ?? $accountName,
                    'release_failed',
                    "Failed to release results for {$examinee_name} - Release ID: {$release_id} - Database Error"
                );
                
                $_SESSION['release_message'] = "Error releasing record for {$examinee_name}";
                $_SESSION['release_status'] = 'error';
            }
        } else {
            logActivity(
                $pdo,
                $_SESSION['user_id'] ?? null,
                $_SESSION['full_name'] ?? $accountName,
                'release_failed',
                "Release ID: {$release_id} not found"
            );

            $_SESSION['release_message'] = "Record not found for release.";
            $_SESSION['release_status'] = 'error';
        }
    } catch (Exception $e) {
        logActivity(
            $pdo,
            $_SESSION['user_id'] ?? null,
            $_SESSION['full_name'] ?? $accountName,
            'release_exception',
            "Exception during release for {$examinee_name} - Release ID: {$release_id} - Error: " . $e->getMessage()
        );
        
        $_SESSION['release_message'] = "Release failed due to an error: " . $e->getMessage();
        $_SESSION['release_status'] = 'error';
    }
}

// Handle bulk release action
if (isset($_POST['bulk_release'])) {
    $selected_ids = $_POST['selected_records'] ?? [];
    
    if (!empty($selected_ids)) {
        $success_count = 0;
        $failed_count = 0;
        $released_names = [];
        $examination_name = '';
        
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_ids as $id) {
                $id = intval($id);
                
                $stmt = $pdo->prepare("SELECT name, examination, exam_date, status, upload_timestamp FROM roravailable WHERE id = ?");
                $stmt->execute([$id]);
                $examinee_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($examinee_data) {
                    // Capture examination name (all should be the same in bulk release)
                    if (empty($examination_name)) {
                        $examination_name = $examinee_data['examination'];
                    }
                    
                    $delete_stmt = $pdo->prepare("DELETE FROM roravailable WHERE id = ?");
                    if ($delete_stmt->execute([$id])) {
                        $success_count++;
                        $released_names[] = $examinee_data['name'];
                        
                        logReleaseActivity(
                            $pdo,
                            $_SESSION['user_id'] ?? null,
                            $_SESSION['full_name'] ?? $accountName,
                            $examinee_data['name'],
                            'bulk',
                            "Released ROR record: Name = {$examinee_data['name']}, Exam = {$examinee_data['examination']}, Date = {$examinee_data['exam_date']}, Status = {$examinee_data['status']}, Uploaded = {$examinee_data['upload_timestamp']}"
                        );
                    } else {
                        $failed_count++;
                    }
                } else {
                    $failed_count++;
                }
            }
            
            $pdo->commit();
            
            // Use the new logBulkReleaseSummary function with examination name
            logBulkReleaseSummary(
                $pdo,
                $_SESSION['user_id'] ?? null,
                $_SESSION['full_name'] ?? $accountName,
                $success_count,
                $failed_count,
                $released_names,
                $examination_name
            );
            
            $_SESSION['release_message'] = "Bulk release completed: {$success_count} successful, {$failed_count} failed";
            $_SESSION['release_status'] = $failed_count > 0 ? 'warning' : 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            
            logActivity(
                $pdo,
                $_SESSION['user_id'] ?? null,
                $_SESSION['full_name'] ?? $accountName,
                'bulk_release_failed',
                "Bulk release failed - Error: " . $e->getMessage()
            );
            
            $_SESSION['release_message'] = "Bulk release failed: " . $e->getMessage();
            $_SESSION['release_status'] = 'error';
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?examination=" . urlencode($_GET['examination'] ?? '') . "&search_name=" . urlencode($_GET['search_name'] ?? ''));
        exit();
    }
}

// Fetch examination list
$sql = "SELECT DISTINCT examination FROM roravailable ORDER BY upload_timestamp DESC";
$result = $pdo->query($sql);
$examinations = $result ? $result->fetchAll(PDO::FETCH_COLUMN) : [];

// Fetch examination counts
$sql_counts = "SELECT examination, COUNT(*) as count FROM roravailable GROUP BY examination";
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
                 FROM roravailable
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Uploaded ROR Data - RILIS</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/viewData.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin_panel.php'; ?>
    
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
        <!-- Main ROR Data View Section -->
        <div id="ror-data" class="content-section active">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-table me-3"></i>View Uploaded ROR Data</h1>
                <p class="text-muted">Report of Rating Issuance Logistics and Inventory System</p>
            </div>

            <!-- Status Messages -->
            <?php if (isset($_SESSION['release_message'])): ?>
                <div class="alert alert-<?= $_SESSION['release_status'] === 'success' ? 'success' : ($_SESSION['release_status'] === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show">
                    <i class="fas fa-<?= $_SESSION['release_status'] === 'success' ? 'check-circle' : ($_SESSION['release_status'] === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
                    <?= htmlspecialchars($_SESSION['release_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php 
                unset($_SESSION['release_message']); 
                unset($_SESSION['release_status']); 
                ?>
            <?php endif; ?>

           
        <!-- Filter Card -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>

        <!-- SEARCH FORM -->
        <form method="get" action="" id="filterForm">
    <div class="row align-items-end">
        <div class="col-md-4">
            <label for="examSelect" class="form-label">
                <i class="fas fa-graduation-cap me-1"></i>Select Examination
            </label>
            <select name="examination" id="examSelect" class="form-select" required onchange="document.getElementById('filterForm').submit()">
                <option value="">-- Choose an examination --</option>
                <?php
                    $sortedExaminations = $examinations;
                    sort($sortedExaminations, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach ($sortedExaminations as $examination):
                ?>
                    <option value="<?= htmlspecialchars($examination) ?>" <?= ($exam === $examination) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($examination) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="searchName" class="form-label">
                <i class="fas fa-search me-1"></i>Search Name
            </label>
            <input type="text" id="searchName" name="search_name" class="form-control" placeholder="Enter name to search" value="<?= htmlspecialchars($search_name) ?>">
        </div>

        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
                <i class="fas fa-search me-2"></i>Search
            </button>
            <button type="button" class="btn btn-success flex-fill" onclick="submitExportForm()">
                <i class="fas fa-file-excel me-1"></i>Export
            </button>
        </div>
    </div>
</form>


       <form method="post" action="export_rorData.php" id="exportForm" style="display:none;">
        <input type="hidden" name="exam" id="exportExam">
        <input type="hidden" name="search_name" id="exportSearch">
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
            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label fw-medium" for="selectAll">
                                Select All Records
                            </label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-warning" onclick="bulkRelease()" disabled id="bulkReleaseBtn">
                            <i class="fas fa-trash-alt me-2"></i>Release Selected
                        </button>
                    </div>
                </div>
            </div>

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
                    <form id="bulkForm" method="post" action="">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50"><input type="checkbox" id="selectAllTable" class="form-check-input"></th>
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
                                            <td>
                                                <input type="checkbox" name="selected_records[]" value="<?= htmlspecialchars($row['id']) ?>" class="form-check-input record-checkbox">
                                            </td>
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
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                        onclick="showReleaseConfirmation(<?= htmlspecialchars($row['id']) ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['examination'])) ?>')">
                                                    <i class="fas fa-trash me-1"></i>Release
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
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
    </div>

    <!-- Hidden form for individual releases -->
    <form id="releaseForm" method="post" action="" style="display: none;">
        <input type="hidden" name="release_id" id="releaseId">
        <input type="hidden" name="examinee_name" id="examineeName">
        <input type="hidden" name="examination" id="examinationName">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/viewData.js"></script>
</body>
</html>