<?php
include 'user_panel.php';
include 'db_connect.php';

$selectedYear = $_GET['year'] ?? null;
$selectedCategory = $_GET['category'] ?? '';

$yearQuery = $conn->query("
    SELECT DISTINCT year_folder FROM (
        SELECT year_folder FROM pdf_mailed
        UNION
        SELECT year_folder FROM pdf_on_file
    ) AS all_years
    ORDER BY year_folder DESC
");


$files = [];
$fileCount = 0;

if ($selectedYear) {
    $table = $selectedCategory === 'on_file' ? 'pdf_on_file' : 'pdf_mailed';

    $stmt = $conn->prepare("SELECT * FROM $table WHERE year_folder = ? ORDER BY file_name ASC");
    $stmt->bind_param("s", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = $result->fetch_all(MYSQLI_ASSOC);
    $fileCount = count($files);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Uploaded PDFs</title>
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
        
        .year-selector {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .year-selector h5 {
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .year-selector .form-select {
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1rem;
            background: white;
            color: #333;
        }
        
        .year-selector .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .stats-summary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .stats-summary h3 {
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .stats-summary p {
            margin: 0;
            opacity: 0.9;
        }
        
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            border-color: #6c757d;
            transform: translateY(-2px);
        }
        
        .file-icon {
            font-size: 1.2rem;
            color: #dc3545;
            margin-right: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-state p {
            margin-bottom: 0;
            font-size: 1.1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        
        .file-name {
            font-weight: 600;
            color: #495057;
        }
        
        .year-badge {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .quick-actions {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .quick-actions h5 {
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .quick-actions .btn {
            margin-right: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-file-pdf me-3"></i>View Uploaded PDFs
            </h1>
            <p class="text-muted">Browse and manage uploaded PDF documents by year</p>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Year & Category Selector -->
                <div class="year-selector">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Select Year to Browse</h5>
                    <form method="get" action="user_pdf_view.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <select name="year" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Select Year --</option>
                                    <?php while ($y = $yearQuery->fetch_assoc()): ?>
                                        <option value="<?= $y['year_folder'] ?>" <?= $selectedYear == $y['year_folder'] ? 'selected' : '' ?>>
                                            <?= $y['year_folder'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="category" class="form-select" onchange="this.form.submit()" required>
                                    <option value="" disabled <?= empty($selectedCategory) ? 'selected' : '' ?>>-- Select Category --</option>
                                    <option value="mailed" <?= $selectedCategory == 'mailed' ? 'selected' : '' ?>>Mailed</option>
                                    <option value="on_file" <?= $selectedCategory == 'on_file' ? 'selected' : '' ?>>On File</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <?php
                if ($selectedYear):
                    $table = ($selectedCategory == 'on_file') ? 'pdf_on_file' : 'pdf_mailed';
                    $stmt = $conn->prepare("SELECT * FROM $table WHERE year_folder = ? ORDER BY file_name ASC");
                    $stmt->bind_param("s", $selectedYear);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $fileCount = $result->num_rows;
                ?>
                <!-- Files Table -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-folder-open me-2"></i>Files from Year <?= htmlspecialchars($selectedYear) ?> (<?= ucfirst($selectedCategory) ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($fileCount > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-file-alt me-2"></i>File Name</th>
                                        <th><i class="fas fa-calendar me-2"></i>Examination Year</th>
                                        <th><i class="fas fa-download me-2"></i>Download</th>
                                        <th><i class="fas fa-cog me-2"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span class="file-name"><?= htmlspecialchars($row['file_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="year-badge"><?= $row['year_folder'] ?></span>
                                        </td>
                                        <td>
                                            <a href="download_pdf.php?id=<?= $row['id'] ?>&category=<?= htmlspecialchars($selectedCategory) ?>" 
                                            class="btn btn-success btn-sm">
                                            <i class="fas fa-download me-1"></i>Download
                                            </a>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="<?= htmlspecialchars($row['file_path']) ?>" class="btn btn-primary btn-sm" target="_blank">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                                <div>
                                                <form method="post" action="delete_pdf.php" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h4>No Files Found</h4>
                            <p>No PDF files were found for the year <?= htmlspecialchars($selectedYear) ?>.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- No Year Selected -->
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <h4>Select a Year</h4>
                            <p>Please select a year from the dropdown above to view PDF files.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar Column -->
            <div class="col-md-4">
                <?php if ($selectedYear): ?>
                <div class="stats-summary">
                    <h3><?= $fileCount ?></h3>
                    <p><i class="fas fa-file-pdf me-2"></i>PDF Files in <?= htmlspecialchars($selectedYear) ?></p>
                </div>
                <?php endif; ?>

                <div class="quick-actions">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    <div class="d-flex flex-column gap-2">
                        <a href="user_pdf_upload.php" class="btn btn-light">
                            <i class="fas fa-upload me-2"></i>Upload New PDFs
                        </a>
                        <a href="user_dashboard.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>File Management
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Click "View" to open PDF in new tab</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Click "Download" to save PDF locally</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Files are organized by examination year</small>
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                <small>Deleted files cannot be recovered</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Loading spinner
        document.querySelector('select[name="year"]').addEventListener('change', function () {
            const loadingText = document.createElement('div');
            loadingText.className = 'text-center mt-3';
            loadingText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading files...';
            this.parentElement.appendChild(loadingText);
        });

        // Confirm delete
        document.querySelectorAll('form[action="delete_pdf.php"]').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const fileName = this.closest('tr').querySelector('.file-name').textContent;
                if (confirm(`Are you sure you want to delete "${fileName}"?\n\nThis action cannot be undone.`)) {
                    this.submit();
                }
            });
        });

        // Hover effect on rows
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function () {
                this.style.backgroundColor = '#f8f9fa';
            });
            row.addEventListener('mouseleave', function () {
                this.style.backgroundColor = '';
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
