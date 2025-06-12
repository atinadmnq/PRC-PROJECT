<?php
session_start();
include 'db_connect.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload ROR Excel File</title>
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

        .sidebar-brand:hover { color: white; }

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

        .nav-menu { padding: 20px 0; }
        .nav-item { margin-bottom: 5px; }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
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

        .upload-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .form-label {
            font-weight: 600;
            color: #578FCA;
        }

        .form-label i {
            margin-right: 8px;
            color: #4285f4;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
        }

        .form-control:focus {
            border-color: #4285f4;
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25);
        }

        .upload-btn {
            background: linear-gradient(135deg, #4285f4 0%, #578FCA 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            white-space: nowrap;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.3);
        }

        .requirements-card {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            border: 1px solid #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin-top: 25px;
        }

        .requirements-title {
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
        }

        .requirements-list li {
            padding: 8px 0;
            color: #424242;
        }

        .requirements-list li i {
            color: #4caf50;
            margin-right: 10px;
        }

        .view-records-btn {
            background: linear-gradient(135deg, #4285f4 0%, #578FCA 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
            text-align: center;
            display: inline-block;
        }

        .view-records-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="img/rilis-logo.png" alt="RILIS" style="height: 35px; margin-right: 3px;">
                RILIS
            </a>
        </div>
        <div class="user-info">
            <div class="user-avatar"><i class="fas fa-user"></i></div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User'); ?></div>
            <small class="text-light"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></small><br>
            <small class="text-light"><?php echo ucfirst($_SESSION['role'] ?? 'User'); ?></small>
        </div>
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <li class="nav-item"><a href="dashboard.php#users" class="nav-link"><i class="fas fa-users"></i>Users</a></li>
                    <li class="nav-item"><a href="dashboard.php#register" class="nav-link"><i class="fas fa-user-plus"></i>Register User</a></li>
                    <li class="nav-item"><a href="dashboard.php#activity" class="nav-link"><i class="fas fa-history"></i>Activity Log</a></li>
                <?php endif; ?>
                <li class="nav-item"><a href="uploadData_ui.php" class="nav-link active"><i class="fas fa-upload"></i>Upload Files</a></li>
                <li class="nav-item"><a href="viewData.php" class="nav-link"><i class="fas fa-eye"></i>View Records</a></li>
                <li class="nav-item"><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="main-content">
        <div class="upload-container">
            <div class="page-header">
                <h1 class="page-title">Upload ROR Excel File</h1>
                <p class="page-subtitle">Upload your Roll of Registrants Excel file to the system</p>
            </div>

            <?php if (isset($_SESSION["message"])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION["message"]; unset($_SESSION["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION["error"])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION["error"]; unset($_SESSION["error"]); ?>
                </div>
            <?php endif; ?>

            <!-- Horizontal Upload Form -->
            <form id="uploadForm" action="upload_data.php" method="POST" enctype="multipart/form-data" class="upload-form d-flex gap-3 align-items-end">
                <div class="flex-grow-1">
                    <label for="excel_file" class="form-label d-block">
                        <i class="fas fa-file-excel"></i>
                        Select Excel File (.xlsx or .xls)
                    </label>
                    <input type="file" name="excel_file" id="excel_file" class="form-control" accept=".xlsx,.xls" required>
                </div>
                <div style="min-width: 160px;">
                    <button type="submit" class="upload-btn">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>

            <div class="requirements-card">
                <h5 class="requirements-title"><i class="fas fa-info-circle"></i> File Format Requirements</h5>
                <p class="text-muted">Please make sure the file matches the required format:</p>
                <ul class="requirements-list">
                    <li><i class="fas fa-check-circle"></i>NO.</li>
                    <li><i class="fas fa-check-circle"></i>NAME</li>
                    <li><i class="fas fa-check-circle"></i>EXAMINATION</li>
                    <li><i class="fas fa-check-circle"></i>EXAM DATE</li>
                </ul>
            </div>

            <a href="viewData.php" class="view-records-btn mt-4">
                <i class="fas fa-eye me-2"></i>
                View Records
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('excel_file');
            if (!fileInput.value) {
                alert('Please select an Excel file to upload.');
                e.preventDefault();
                return;
            }
            if (!confirm('Are you sure you want to upload this file?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
