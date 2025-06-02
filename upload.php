<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Database configuration - Update these with your database credentials
$host = 'localhost';
$dbname = 'prc_release_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Activity logging function
function logActivity($pdo, $accountName, $action, $description, $clientName = '', $fileName = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (account_name, action, description, client_name, file_name, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$accountName, $action, $description, $clientName, $fileName]);
    } catch(PDOException $e) {
        // Log error but don't break the main functionality
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Function to get the last upload activity for undo
function getLastUploadActivity($pdo, $accountName) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE account_name = ? AND action = 'upload' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$accountName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return false;
    }
}

// Handle undo upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo_upload'])) {
    $response = array();
    
    try {
        $accountName = $_SESSION['account_name'] ?? $_SESSION['email'] ?? 'Unknown';
        $lastUpload = getLastUploadActivity($pdo, $accountName);
        
        if (!$lastUpload) {
            throw new Exception('No recent upload found to undo');
        }
        
        // Get the timestamp from the last upload (within last 24 hours for safety)
        $uploadTime = new DateTime($lastUpload['created_at']);
        $now = new DateTime();
        $timeDiff = $now->diff($uploadTime);
        
        // Only allow undo within 24 hours
        if ($timeDiff->days > 0) {
            throw new Exception('Upload is too old to undo (more than 24 hours)');
        }
        
        // Get clients added after the upload timestamp
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM client WHERE created_at >= ?");
        $stmt->execute([$lastUpload['created_at']]);
        $clientCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($clientCount == 0) {
            throw new Exception('No clients found to remove from this upload');
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete clients added after the upload timestamp
        $stmt = $pdo->prepare("DELETE FROM client WHERE created_at >= ?");
        $stmt->execute([$lastUpload['created_at']]);
        $deletedCount = $stmt->rowCount();
        
        // Log the undo activity
        logActivity($pdo, $accountName, 'undo_upload', "Undid upload: removed $deletedCount client records", '', $lastUpload['file_name']);
        
        // Commit transaction
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = "Successfully undid upload. Removed $deletedCount client records.";
        $response['deleted_count'] = $deletedCount;
        
    } catch (Exception $e) {
        // Rollback transaction if it was started
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        $response['success'] = false;
        $response['message'] = 'Undo failed: ' . $e->getMessage();
        
        // Log failed undo activity
        logActivity($pdo, $accountName ?? 'Unknown', 'undo_upload_error', 'Undo upload failed: ' . $e->getMessage());
    }
    
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Simple Excel reader function using built-in PHP
function readExcelFile($filePath) {
    $data = [];
    
    // For .xlsx files (modern Excel format)
    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'xlsx') {
        // Create temporary CSV file
        $csvFile = tempnam(sys_get_temp_dir(), 'excel_') . '.csv';
        
        // Use a simple approach - convert xlsx to CSV using available tools
        // Note: This is a basic implementation. For production, consider using a proper library
        
        // Try to read as XML (basic XLSX structure)
        $zip = new ZipArchive();
        if ($zip->open($filePath) === TRUE) {
            $sharedStrings = [];
            $worksheetData = '';
            
            // Read shared strings
            $sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedStringsXML) {
                $xml = simplexml_load_string($sharedStringsXML);
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string)$si->t;
                }
            }
            
            // Read worksheet data
            $worksheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            
            if ($worksheetData) {
                $xml = simplexml_load_string($worksheetData);
                $rowIndex = 0;
                
                foreach ($xml->sheetData->row as $row) {
                    $rowData = [];
                    $colIndex = 0;
                    
                    foreach ($row->c as $cell) {
                        $value = '';
                        if (isset($cell->v)) {
                            $cellValue = (string)$cell->v;
                            
                            // Check if it's a shared string
                            if (isset($cell['t']) && (string)$cell['t'] === 's') {
                                $value = isset($sharedStrings[$cellValue]) ? $sharedStrings[$cellValue] : $cellValue;
                            } else {
                                $value = $cellValue;
                            }
                        }
                        $rowData[] = $value;
                        $colIndex++;
                    }
                    
                    // Ensure we have at least 2 columns
                    while (count($rowData) < 2) {
                        $rowData[] = '';
                    }
                    
                    $data[] = $rowData;
                    $rowIndex++;
                }
            }
        }
    } 
    // For .xls files (older Excel format) - convert to CSV first
    else if (pathinfo($filePath, PATHINFO_EXTENSION) === 'xls') {
        // This is a simplified approach for .xls files
        // In production, you might want to use a more robust solution
        throw new Exception('.xls format requires additional processing. Please save your file as .xlsx format.');
    }
    
    return $data;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    $response = array();
    
    try {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $file = $_FILES['excelFile'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileError = $file['error'];
        
        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }
        
        // Check file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExt, ['xlsx'])) {
            throw new Exception('Only .xlsx Excel files are supported. Please convert .xls files to .xlsx format.');
        }
        
        $uploadPath = $uploadDir . uniqid() . '_' . $fileName;
        
        if (!move_uploaded_file($fileTmpName, $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        // Read Excel data
        $excelData = readExcelFile($uploadPath);
        
        if (empty($excelData)) {
            throw new Exception('No data found in Excel file');
        }
        
        $insertedCount = 0;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Prepare insert statement (include created_at for tracking)
        $stmt = $pdo->prepare("INSERT INTO client (profession, full_name, first_name, last_name, created_at) VALUES (?, ?, ?, ?, NOW())");
        
        // Process each row (skip first row if it's headers)
        $startRow = 1; // Skip header row
        for ($i = $startRow; $i < count($excelData); $i++) {
            $row = $excelData[$i];
            
            $profession = isset($row[0]) ? trim($row[0]) : '';
            $fullName = isset($row[1]) ? trim($row[1]) : '';
            
            // Skip empty rows
            if (empty($profession) && empty($fullName)) {
                continue;
            }
            
            // Parse full name to extract first and last names
            $firstName = '';
            $lastName = '';
            
            if (!empty($fullName)) {
                // Expected format: "Lastname, Firstname"
                if (strpos($fullName, ',') !== false) {
                    $nameParts = explode(',', $fullName, 2);
                    $lastName = trim($nameParts[0]);
                    $firstName = trim($nameParts[1]);
                } else {
                    // If no comma, treat as full name
                    $firstName = $fullName;
                }
            }
            
            // Insert into database
            $stmt->execute([$profession, $fullName, $firstName, $lastName]);
            $insertedCount++;
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clean up uploaded file
        unlink($uploadPath);
        
        // Log successful upload activity
        logActivity($pdo, $_SESSION['account_name'] ?? $_SESSION['email'] ?? 'Unknown', 'upload', "File uploaded successfully: $insertedCount records imported", '', $fileName);
        
        $response['success'] = true;
        $response['message'] = "Successfully imported $insertedCount records from Excel file";
        $response['inserted_count'] = $insertedCount;
        
    } catch (Exception $e) {
        // Rollback transaction if it was started
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        $response['success'] = false;
        $response['message'] = 'Error: ' . $e->getMessage();
        
        // Clean up file if it exists
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        
        // Log failed upload activity
        logActivity($pdo, $_SESSION['account_name'] ?? $_SESSION['email'] ?? 'Unknown', 'upload_error', 'File upload failed: ' . $e->getMessage(), '', $fileName ?? 'Unknown');
    }
    
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get last upload info for undo button
$accountName = $_SESSION['account_name'] ?? $_SESSION['email'] ?? 'Unknown';
$lastUpload = getLastUploadActivity($pdo, $accountName);
$canUndo = false;
$undoInfo = '';
$debugInfo = '';

if ($lastUpload) {
    $uploadTime = new DateTime($lastUpload['created_at']);
    $now = new DateTime();
    $timeDiff = $now->diff($uploadTime);
    
    // Check if upload is within 24 hours
    if ($timeDiff->days == 0) {
        $canUndo = true;
        $timeAgo = '';
        if ($timeDiff->h > 0) {
            $timeAgo = $timeDiff->h . ' hour(s) ago';
        } else {
            $timeAgo = $timeDiff->i . ' minute(s) ago';
        }
        $undoInfo = "Last upload: {$lastUpload['file_name']} ($timeAgo)";
    } else {
        $debugInfo = "Last upload was {$timeDiff->days} day(s) ago - too old to undo";
    }
} else {
    $debugInfo = "No previous uploads found for this account";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Files - PRC Release</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="upload.css" rel="stylesheet">
 
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-shield-alt me-2"></i>
                PRC Release
            </a>
        </div>
        
        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
            <small class="text-light">Administrator</small>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="release.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        Add Release
                    </a>
                </li>
                <li class="nav-item">
                    <a href="upload.php" class="nav-link active">
                        <i class="fas fa-upload"></i>
                        Upload Files
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?logout=1" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Upload Section -->
        <div id="upload">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-upload me-3"></i>
                    Upload Client Data
                </h1>
                <p class="text-muted">Upload Excel file with client information to database</p>
            </div>

            <!-- Undo Section -->
            <?php if ($canUndo): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Undo Available:</strong> <?php echo htmlspecialchars($undoInfo); ?>
                        </div>
                        <button type="button" class="btn btn-warning btn-sm" id="undoBtn" onclick="undoLastUpload()">
                            <i class="fas fa-undo me-2"></i>
                            Undo Last Upload
                        </button>
                    </div>
                </div>
            </div>
            <?php elseif (!empty($debugInfo)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-secondary d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Undo Status:</strong> <?php echo htmlspecialchars($debugInfo); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Left Column - Format Requirements -->
                <div class="col-lg-5">
                    <div class="format-container">
                        <div class="excel-format-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Format Requirements</h6>
                            <ul class="format-list">
                                <li><strong>Column A:</strong> Profession</li>
                                <li><strong>Column B:</strong> Full Name<br><small class="text-muted">(Format: "Lastname, Firstname")</small></li>
                                <li><strong>Row 1:</strong> Headers (skipped)</li>
                            </ul>
                            <div class="format-example">
                                <strong>Example:</strong><br>
                                <small>
                                Profession | Full Name<br>
                                Engineer | Smith, John<br>
                                Doctor | Johnson, Mary
                                </small>
                            </div>
                        </div>
                        
                        <!-- Important Notice -->
                        <div class="alert alert-warning p-2" role="alert">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <small><strong>Note:</strong> Only .xlsx files supported</small>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Upload Form -->
                <div class="col-lg-7">
                    <div class="upload-container">
                        <form id="excelUploadForm" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea">
                        <div class="mb-3">
                            <i class="fas fa-file-excel fa-3x text-success"></i>
                        </div>
                        <h5>Drop Excel file here or click to browse</h5>
                        <p class="text-muted">Supported formats: .xlsx only</p>
                        <input type="file" class="form-control d-none" id="excelFile" name="excelFile" accept=".xlsx">
                        <button type="button" class="btn btn-outline-success" onclick="document.getElementById('excelFile').click()">
                            <i class="fas fa-folder-open me-2"></i>
                            Browse Excel File (.xlsx)
                        </button>
                    </div>
                    <br><br>
                    
                    <div id="selectedFile" class="mb-3" style="display: none;">
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div id="fileInfo">
                                <i class="fas fa-file-excel me-2"></i>
                                <span id="fileName"></span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFile()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-success" id="uploadBtn">
                                    <i class="fas fa-database me-2"></i>
                                    Import to Database
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearFile()">
                                    <i class="fas fa-trash me-2"></i>
                                    Clear
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Upload Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="modalMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="handleModalClose()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal for Undo -->
    <div class="modal fade" id="undoConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Undo Upload
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Warning:</strong> This action will permanently delete all client records from your last upload.</p>
                    <p class="text-muted">Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="confirmUndo()">
                        <i class="fas fa-undo me-2"></i>
                        Yes, Undo Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const excelFile = document.getElementById('excelFile');
        const selectedFileDiv = document.getElementById('selectedFile');
        const fileName = document.getElementById('fileName');
        const uploadForm = document.getElementById('excelUploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        
        // Drag and drop functionality
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelection(files[0]);
            }
        });
        
        excelFile.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
        
        function handleFileSelection(file) {
            // Check file type
            const fileExt = file.name.toLowerCase().split('.').pop();
            
            if (fileExt !== 'xlsx') {
                alert('Please select a valid .xlsx Excel file. .xls files are not supported - please convert to .xlsx format first.');
                return;
            }
            
            fileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            selectedFileDiv.style.display = 'block';
        }
        
        function clearFile() {
            excelFile.value = '';
            selectedFileDiv.style.display = 'none';
        }
        
        // Handle form submission
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!excelFile.files.length) {
                alert('Please select an Excel file to upload');
                return;
            }
            
            const formData = new FormData(this);
            
            // Update button state
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            uploadBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Show result modal
                const modal = new bootstrap.Modal(document.getElementById('resultModal'));
                const modalTitle = document.getElementById('modalTitle');
                const modalMessage = document.getElementById('modalMessage');
                
                if (data.success) {
                    modalTitle.textContent = 'Import Successful';
                    modalTitle.className = 'modal-title text-success';
                    modalMessage.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>' + data.message;
                    clearFile();
                } else {
                    modalTitle.textContent = 'Import Failed';
                    modalTitle.className = 'modal-title text-danger';
                    modalMessage.innerHTML = '<i class="fas fa-exclamation-circle text-danger me-2"></i>' + data.message;
                }
                
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during upload. Please try again.');
            })
            .finally(() => {
                // Reset button state
                uploadBtn.innerHTML = '<i class="fas fa-database me-2"></i>Import to Database';
                uploadBtn.disabled = false;
            });
        });

        // Undo functionality
        function undoLastUpload() {
            const modal = new bootstrap.Modal(document.getElementById('undoConfirmModal'));
            modal.show();
        }

        function confirmUndo() {
            const undoBtn = document.getElementById('undoBtn');
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('undoConfirmModal'));
            
            // Close confirmation modal
            confirmModal.hide();
            
            // Update button state
            undoBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            undoBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('undo_upload', '1');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Show result modal
                const modal = new bootstrap.Modal(document.getElementById('resultModal'));
                const modalTitle = document.getElementById('modalTitle');
                const modalMessage = document.getElementById('modalMessage');
                
                if (data.success) {
                    modalTitle.textContent = 'Undo Successful';
                    modalTitle.className = 'modal-title text-success';
                    modalMessage.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>' + data.message;
                } else {
                    modalTitle.textContent = 'Undo Failed';
                    modalTitle.className = 'modal-title text-danger';
                    modalMessage.innerHTML = '<i class="fas fa-exclamation-circle text-danger me-2"></i>' + data.message;
                }
                
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during undo. Please try again.');
            })
            .finally(() => {
                // Reset button state
                undoBtn.innerHTML = '<i class="fas fa-undo me-2"></i>Undo Last Upload';
                undoBtn.disabled = false;
            });
        }

        function handleModalClose() {
            // Refresh page if undo was successful to update the undo button visibility
            if (document.getElementById('modalTitle').textContent === 'Undo Successful') {
                location.reload();
            }
        }
    </script>
</body>
</html>