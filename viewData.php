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

// Check if user is logged in as staff
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$accountName = $_SESSION['account_name'] ?? $_SESSION['email'] ?? $_SESSION['full_name'] ?? 'Unknown User';

// Get user's full name if not already set
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) $_SESSION['full_name'] = $user['full_name'];
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
        
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_ids as $id) {
                $id = intval($id);
                
                $stmt = $pdo->prepare("SELECT name, examination, exam_date, status, upload_timestamp FROM roravailable WHERE id = ?");
                $stmt->execute([$id]);
                $examinee_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($examinee_data) {
                    $delete_stmt = $pdo->prepare("DELETE FROM roravailable WHERE id = ?");
                    if ($delete_stmt->execute([$id])) {
                        $success_count++;
                        
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
            
            logActivity(
                $pdo,
                $_SESSION['user_id'] ?? null,
                $_SESSION['full_name'] ?? $accountName,
                'bulk_release_summary',
                "Bulk release completed - Success: {$success_count}, Failed: {$failed_count}"
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

// Fetch data based on filter
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

// Fetch activity logs
try {
    $activity_logs = $pdo->query("
        SELECT 
            al.*,
            COALESCE(al.user_name, al.account_name, al.username, 'Unknown User') as full_name
        FROM activity_log al 
        ORDER BY COALESCE(al.created_at, al.timestamp) DESC 
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity_logs = [];
    error_log("Activity log query failed: " . $e->getMessage());
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
    <link href="viewData.css" rel="stylesheet">
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
                                                <form method="post" action="" onsubmit="return confirmRelease('<?= htmlspecialchars($row['name']) ?>');" class="d-inline">
                                                    <input type="hidden" name="release_id" value="<?= htmlspecialchars($row['id']) ?>">
                                                    <input type="hidden" name="examinee_name" value="<?= htmlspecialchars($row['name']) ?>">
                                                    <input type="hidden" name="examination" value="<?= htmlspecialchars($row['examination']) ?>">
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

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmReleaseModal" tabindex="-1" aria-labelledby="confirmReleaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmReleaseModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>Confirm Release
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to release the following record(s)?</p>
                    <div id="releaseConfirmationContent"></div>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This action will permanently delete the record(s) from the system and cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmReleaseBtn">
                        <i class="fas fa-trash me-2"></i>Confirm Release
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation functionality
        document.querySelectorAll('.nav-link[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
                document.getElementById(this.getAttribute('data-section')).classList.add('active');
            });
        });

        document.querySelectorAll('button[data-section]').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                const targetSection = this.getAttribute('data-section');
                document.querySelector(`.nav-link[data-section="${targetSection}"]`).classList.add('active');
                document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
                document.getElementById(targetSection).classList.add('active');
            });
        });

        // Activity log filtering
        if (document.getElementById('activityFilter')) {
            document.getElementById('activityFilter').addEventListener('change', function() {
                const filter = this.value;
                document.querySelectorAll('.activity-row').forEach(row => {
                    row.style.display = (filter === 'all' || row.getAttribute('data-action') === filter) ? '' : 'none';
                });
            });
        }

        // Enhanced confirmation function for individual releases
        function confirmRelease(examineeName) {
            return confirm(`Are you sure you want to release (delete) the record for "${examineeName}"?\n\nThis action cannot be undone.`);
        }

        // Checkbox functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllMain = document.getElementById('selectAll');
            const selectAllTable = document.getElementById('selectAllTable');
            const recordCheckboxes = document.querySelectorAll('.record-checkbox');
            const bulkReleaseBtn = document.getElementById('bulkReleaseBtn');

            // Function to update bulk release button state
            function updateBulkReleaseButton() {
                const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
                if (bulkReleaseBtn) {
                    bulkReleaseBtn.disabled = checkedBoxes.length === 0;
                }
            }

            // Select all functionality
            if (selectAllMain) {
                selectAllMain.addEventListener('change', function() {
                    recordCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    if (selectAllTable) selectAllTable.checked = this.checked;
                    updateBulkReleaseButton();
                });
            }

            if (selectAllTable) {
                selectAllTable.addEventListener('change', function() {
                    recordCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    if (selectAllMain) selectAllMain.checked = this.checked;
                    updateBulkReleaseButton();
                });
            }

            // Individual checkbox functionality
            recordCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(recordCheckboxes).every(cb => cb.checked);
                    const anyChecked = Array.from(recordCheckboxes).some(cb => cb.checked);
                    
                    if (selectAllMain) selectAllMain.checked = allChecked;
                    if (selectAllTable) selectAllTable.checked = allChecked;
                    
                    updateBulkReleaseButton();
                });
            });

            // Initialize button state
            updateBulkReleaseButton();
        });

        // Bulk release functionality
        function bulkRelease() {
            const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one record to release.');
                return;
            }

            // Get selected record details for confirmation
            const selectedRecords = [];
            checkedBoxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const name = row.querySelector('td:nth-child(3) span').textContent;
                const examination = row.querySelector('td:nth-child(4)').textContent;
                selectedRecords.push({name, examination});
            });

            // Show confirmation modal
            showBulkReleaseModal(selectedRecords, checkedBoxes);
        }

        function showBulkReleaseModal(selectedRecords, checkedBoxes) {
            const modal = new bootstrap.Modal(document.getElementById('confirmReleaseModal'));
            const content = document.getElementById('releaseConfirmationContent');
            
            let html = `<div class="alert alert-info">
                <strong>${selectedRecords.length}</strong> record(s) selected for release:
            </div>
            <div class="list-group" style="max-height: 200px; overflow-y: auto;">`;
            
            selectedRecords.slice(0, 10).forEach(record => {
                html += `<div class="list-group-item">
                    <strong>${record.name}</strong><br>
                    <small class="text-muted">${record.examination}</small>
                </div>`;
            });
            
            if (selectedRecords.length > 10) {
                html += `<div class="list-group-item text-center text-muted">
                    ... and ${selectedRecords.length - 10} more record(s)
                </div>`;
            }
            
            html += '</div>';
            content.innerHTML = html;
            
            // Set up confirm button
            document.getElementById('confirmReleaseBtn').onclick = function() {
                // Create form with selected IDs
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                // Add bulk release indicator
                const bulkInput = document.createElement('input');
                bulkInput.type = 'hidden';
                bulkInput.name = 'bulk_release';
                bulkInput.value = '1';
                form.appendChild(bulkInput);
                
                // Add selected record IDs
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_records[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            };
            
            modal.show();
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-dismissible')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    </script>
</body>
</html>