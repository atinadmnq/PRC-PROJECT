<!-- rtsDataUpload.php-->
<?php
session_start();
include 'db_connect.php';

require 'vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $file = $_FILES["excel_file"]["tmp_name"];
    
    if (!file_exists($file)) {
        $_SESSION["error"] = "File not found.";
        header("Location: rtsDataUpload.php");
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
            // logActivity("Uploaded ROR Excel with $recordsInserted records (Timestamp: $upload_timestamp)", "admin");
            $_SESSION["message"] = "Excel file uploaded successfully!";
            $_SESSION["last_upload_timestamp"] = $upload_timestamp;
            $_SESSION["last_upload_ids"] = $inserted_ids;
        } else {
            $_SESSION["error"] = "No records inserted.";
        }

    } catch (Exception $e) {
        $_SESSION["error"] = "Error reading Excel file: " . $e->getMessage();
    }

} else {
    $_SESSION["error"] = "No file uploaded.";
}

header("Location: uploadData_ui.php");
exit();
?>
