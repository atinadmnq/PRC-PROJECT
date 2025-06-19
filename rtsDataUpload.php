<?php
session_start();
include 'db_connect.php';
require_once 'activity_logger.php'; 

require 'vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;

// Function to log RTS upload activity
function logRTSUpload($pdo, $userId, $userName, $fileName, $recordCount) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, description, timestamp) 
            VALUES (?, ?, 'upload_rts', ?, NOW())
        ");
        
        $description = "Uploaded RTS file: {$fileName} with {$recordCount} records processed";
        $stmt->execute([$userId, $userName, $description]);
    } catch (Exception $e) {
        // Log the error but don't break the main process
        error_log("Failed to log RTS upload activity: " . $e->getMessage());
    }
}

// Function to log general activity (for failures)
function logActivity($pdo, $userId, $userName, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, description, timestamp) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $userName, $action, $description]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Database connection for logging (using PDO)
try {
    $pdo = new PDO("mysql:host=localhost;dbname=prc_release_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $file = $_FILES["excel_file"]["tmp_name"];
    $fileName = $_FILES["excel_file"]["name"];
    
    if (!file_exists($file)) {
        // Log failed upload attempt
        logActivity(
            $pdo, 
            $_SESSION['user_id'] ?? null, 
            $_SESSION['full_name'] ?? 'Unknown User', 
            'upload_rts', 
            "Failed to upload RTS file: {$fileName} - File not found"
        );
        
        $_SESSION["error"] = "File not found.";
        header("Location: rts_ui.php");
        exit();
    }
    
    $upload_timestamp = date('Y-m-d H:i:s'); 
    
    try {
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $recordsInserted = 0;
        $inserted_ids = [];

        // Skip header rows (row 1 and row 2 are titles), actual header starts at row 3
        for ($i = 3; $i < count($rows); $i++) {
            $data = $rows[$i];

            // Stop if empty row
            if (empty($data[0]) && empty($data[1]) && empty($data[2]) && empty($data[3])) {
                continue;
            }

            // Read values
            $name = $data[1] ?? 'N/A';
            $examination = $data[2] ?? 'N/A';
            $exam_date = $data[3] ?? 'N/A';
            $status = 'pending';

            // Insert into database
            $stmt = $conn->prepare("INSERT INTO rts_data_onhold (name, examination, exam_date, upload_timestamp, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $examination, $exam_date, $upload_timestamp, $status);
            
            if ($stmt->execute()) {
                $recordsInserted++;
                $inserted_ids[] = $conn->insert_id;
            }
            $stmt->close();
        }

        if ($recordsInserted > 0) {
            // Log successful RTS upload
            logRTSUpload(
                $pdo, 
                $_SESSION['user_id'] ?? null, 
                $_SESSION['full_name'] ?? 'Unknown User', 
                $fileName, 
                $recordsInserted
            );
            
            $_SESSION["message"] = "Excel file uploaded successfully! {$recordsInserted} records processed.";
            $_SESSION["last_upload_timestamp"] = $upload_timestamp;
            $_SESSION["last_upload_ids"] = $inserted_ids;
        } else {
            // Log failed upload (no records)
            logActivity(
                $pdo, 
                $_SESSION['user_id'] ?? null, 
                $_SESSION['full_name'] ?? 'Unknown User', 
                'upload_rts', 
                "Failed to upload RTS file: {$fileName} - No records inserted"
            );
            
            $_SESSION["error"] = "No records inserted.";
        }

    } catch (Exception $e) {
        // Log failed upload attempt with error details
        logActivity(
            $pdo, 
            $_SESSION['user_id'] ?? null, 
            $_SESSION['full_name'] ?? 'Unknown User', 
            'upload_rts', 
            "Failed to upload RTS file: {$fileName} - Error: " . $e->getMessage()
        );
        
        $_SESSION["error"] = "Error reading Excel file: " . $e->getMessage();
    }

} else {
    // Log invalid upload attempt
    logActivity(
        $pdo, 
        $_SESSION['user_id'] ?? null, 
        $_SESSION['full_name'] ?? 'Unknown User', 
        'upload_rts', 
        "Invalid RTS upload attempt - No file uploaded"
    );
    
    $_SESSION["error"] = "No file uploaded.";
}

header("Location: rts_ui.php");
exit();
?>