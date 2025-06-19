<?php
session_start();
require_once 'activity_logger.php'; // Include the logging functions
include 'db_connect.php';

require 'vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $file = $_FILES["excel_file"]["tmp_name"];
    $fileName = $_FILES["excel_file"]["name"] ?? 'Unknown File';
    
    if (!file_exists($file)) {
        $_SESSION["error"] = "File not found.";
        
        // Log failed upload attempt
        logActivity(
            $conn, 
            $_SESSION['user_id'] ?? null, 
            $_SESSION['full_name'] ?? 'Unknown User', 
            'upload_ror', 
            "Failed to upload ROR file: {$fileName} - Error: File not found"
        );
        
        header("Location: update_data.php");
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
            $stmt = $conn->prepare("INSERT INTO roravailable (name, examination, exam_date, upload_timestamp, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $examination, $exam_date, $upload_timestamp, $status);
            
            if ($stmt->execute()) {
                $recordsInserted++;
                $inserted_ids[] = $conn->insert_id;
            }
            $stmt->close();
        }

        if ($recordsInserted > 0) {
            // Log successful ROR upload
            logRORUpload(
                $conn, 
                $_SESSION['user_id'] ?? null, 
                $_SESSION['full_name'] ?? 'Unknown User', 
                $fileName, 
                $recordsInserted
            );
            
            $_SESSION["message"] = "Excel file uploaded successfully! {$recordsInserted} records processed.";
            $_SESSION["last_upload_timestamp"] = $upload_timestamp;
            $_SESSION["last_upload_ids"] = $inserted_ids;
        } else {
            $_SESSION["error"] = "No records inserted.";
            
            // Log failed upload attempt - no records processed
            logActivity(
                $conn, 
                $_SESSION['user_id'] ?? null, 
                $_SESSION['full_name'] ?? 'Unknown User', 
                'upload_ror', 
                "Failed to upload ROR file: {$fileName} - Error: No records processed"
            );
        }

    } catch (Exception $e) {
        $_SESSION["error"] = "Error reading Excel file: " . $e->getMessage();
        
        // Log failed upload attempt
        logActivity(
            $conn, 
            $_SESSION['user_id'] ?? null, 
            $_SESSION['full_name'] ?? 'Unknown User', 
            'upload_ror', 
            "Failed to upload ROR file: {$fileName} - Error: " . $e->getMessage()
        );
    }

} else {
    $_SESSION["error"] = "No file uploaded.";
    
    // Log failed upload attempt - no file provided
    logActivity(
        $conn, 
        $_SESSION['user_id'] ?? null, 
        $_SESSION['full_name'] ?? 'Unknown User', 
        'upload_ror', 
        "Failed to upload ROR file - Error: No file uploaded"
    );
}

header("Location: uploadData_ui.php");
exit();
?>