<?php
include 'admin_panel.php';
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "prc_release_db";
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$uploadSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_files'])) {
    $year = $_POST['year'];
    $uploadDir = "uploads/$year/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $stmt = $conn->prepare("INSERT INTO pdf_files (file_name, file_path, year_folder) VALUES (?, ?, ?)");
    foreach ($_FILES['pdf_files']['name'] as $index => $name) {
        $tmpName = $_FILES['pdf_files']['tmp_name'][$index];
        if ($_FILES['pdf_files']['error'][$index] === UPLOAD_ERR_OK) {
            $safeName = uniqid() . "_" . basename($name);
            $targetPath = $uploadDir . $safeName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $stmt->bind_param("sss", $name, $targetPath, $year);
                $stmt->execute();
                $uploadSuccess = true;
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Scanned ROR PDFs</title>
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
        
        .upload-zone {
            border: 2px dashed #007bff;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: #f8f9ff;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .upload-zone:hover {
            border-color: #0056b3;
            background: #e7f3ff;
        }
        
        .upload-zone.dragover {
            border-color: #28a745;
            background: #e8f5e8;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #007bff;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid #007bff;
            color: #007bff;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .file-info {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            display: none;
        }
        
        .file-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-item i {
            color: #dc3545;
            margin-right: 10px;
        }
        
        .success-message {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .success-message i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .info-card {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-card h5 {
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .info-card ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .info-card li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-file-pdf me-3"></i>Upload Scanned ROR PDFs
            </h1>
            <p class="text-muted">Upload and manage PDF documents for ROR records</p>
        </div>
        
        <!-- Success Message -->
        <?php if (isset($uploadSuccess) && $uploadSuccess): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div>Files uploaded successfully!</div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Upload Form Card -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-upload me-2"></i>Upload PDF Files
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data" id="uploadForm">
                            <!-- Year Selection -->
                            <div class="mb-4">
                                <label for="year" class="form-label">
                                    <i class="fas fa-calendar-alt me-2"></i>Select Year
                                </label>
                                <input type="number" class="form-control" id="year" name="year" 
                                       min="2000" max="2099" value="<?php echo date('Y'); ?>" required>
                            </div>
                            
                            <!-- File Upload Zone -->
                            <div class="upload-zone" id="uploadZone">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h4>Drag & Drop PDF Files Here</h4>
                                <p class="text-muted">Or click to select files</p>
                                <input type="file" name="pdf_files[]" id="pdfFiles" 
                                       accept="application/pdf" multiple required style="display: none;">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('pdfFiles').click()">
                                    <i class="fas fa-folder-open me-2"></i>Select Files
                                </button>
                            </div>
                            
                            <!-- File Info Display -->
                            <div class="file-info" id="fileInfo">
                                <h6><i class="fas fa-info-circle me-2"></i>Selected Files:</h6>
                                <div class="file-list" id="fileList"></div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="text-end">
                                <button type="submit" name="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload PDFs
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Quick Actions Card -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <form action="pdf_view.php" method="get">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-folder-open me-2"></i>View Uploaded Files
                                </button>
                            </form>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Guidelines Card -->
                <div class="info-card">
                    <h5><i class="fas fa-info-circle me-2"></i>Upload Guidelines</h5>
                    <ul>
                        <li>Only PDF files are accepted</li>
                        <li>Multiple files can be uploaded at once</li>
                        <li>Files are organized by year</li>
                        <li>Maximum file size: 10MB per file</li>
                        <li>Supported formats: PDF only</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload functionality
        const uploadZone = document.getElementById('uploadZone');
        const pdfFiles = document.getElementById('pdfFiles');
        const fileInfo = document.getElementById('fileInfo');
        const fileList = document.getElementById('fileList');
        
        // Click to select files
        uploadZone.addEventListener('click', function(e) {
            if (e.target.type !== 'file') {
                pdfFiles.click();
            }
        });
        
        // Drag and drop functionality
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            pdfFiles.files = files;
            displayFiles(files);
        });
        
        // File selection change
        pdfFiles.addEventListener('change', function() {
            displayFiles(this.files);
        });
        
        // Display selected files
        function displayFiles(files) {
            if (files.length > 0) {
                fileInfo.style.display = 'block';
                fileList.innerHTML = '';
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.innerHTML = `
                        <i class="fas fa-file-pdf"></i>
                        <span>${file.name}</span>
                        <small class="text-muted ms-auto">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    `;
                    fileList.appendChild(fileItem);
                }
            } else {
                fileInfo.style.display = 'none';
            }
        }
        
        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const files = pdfFiles.files;
            if (files.length === 0) {
                e.preventDefault();
                alert('Please select at least one PDF file to upload.');
                return;
            }
            
            // Check file types
            for (let i = 0; i < files.length; i++) {
                if (files[i].type !== 'application/pdf') {
                    e.preventDefault();
                    alert('Only PDF files are allowed.');
                    return;
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>