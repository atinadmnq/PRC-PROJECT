<?php
session_start();
include 'db_connect.php';
require_once 'activity_logger.php'; 
require 'vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;

// Normalize examination titles
function normalizeExamination($input) {
    $input = strtolower(trim($input));
    $mapping = [
        'nurses' => 'NURSE',
        'nurse' => 'NURSE',
        'ree' => 'REGISTERED ELECTRICAL ENGINEER',
        'registered electrical eng' => 'REGISTERED ELECTRICAL ENGINEER',
        'registered electrical engineer' => 'REGISTERED ELECTRICAL ENGINEER',
        'electrical engineer' => 'REGISTERED ELECTRICAL ENGINEER',
        'rme' => 'REGISTERED MASTER ELECTRICIAN',
        'registered master electrician' => 'REGISTERED MASTER ELECTRICIAN',
        'agriculturists' => 'AGRICULTURIST',
        'agriculture' => 'AGRICULTURIST',
        'agriculturist' => 'AGRICULTURIST',
        'teachers' => 'PROFESSIONAL TEACHERS',
        'professional teacher' => 'PROFESSIONAL TEACHERS',
        'rad tech' => 'RADIOLOGIC TECHNOLOGIST',
        'radiologic technologist' => 'RADIOLOGIC TECHNOLOGIST',
        'dentists' => 'DENTIST',
        'dentist' => 'DENTIST',
        'psychometricians' => 'PSYCHOMETRICIAN',
        'psychometrician' => 'PSYCHOMETRICIAN',
       
    ];
    return $mapping[$input] ?? strtoupper($input);
}


// PDO connection for activity logging
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
        logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_rts', "Failed to upload RTS file: {$fileName} - File not found");
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

        // Start reading from row 4
        for ($i = 3; $i < count($rows); $i++) {
            $data = $rows[$i];

            if (empty($data[0]) && empty($data[1]) && empty($data[2]) && empty($data[3])) {
                continue;
            }

            $name = $data[1] ?? 'N/A';
            $raw_exam = $data[2] ?? 'N/A';
            $examination = normalizeExamination($raw_exam);
            $exam_date = $data[3] ?? 'N/A';
            $status = 'pending';

            $stmt = $conn->prepare("INSERT INTO rts_data_onhold (name, examination, exam_date, upload_timestamp, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $examination, $exam_date, $upload_timestamp, $status);

            if ($stmt->execute()) {
                $recordsInserted++;
                $inserted_ids[] = $conn->insert_id;
            }

            $stmt->close();
        }

        if ($recordsInserted > 0) {
            logRTSUpload($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', $fileName, $recordsInserted);
            $_SESSION["message"] = "Excel file uploaded successfully! {$recordsInserted} records processed.";
            $_SESSION["last_upload_timestamp"] = $upload_timestamp;
            $_SESSION["last_upload_ids"] = $inserted_ids;
        } else {
            logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_rts', "Failed to upload RTS file: {$fileName} - No records inserted");
            $_SESSION["error"] = "No records inserted.";
        }

    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_rts', "Failed to upload RTS file: {$fileName} - Error: " . $e->getMessage());
        $_SESSION["error"] = "Error reading Excel file: " . $e->getMessage();
    }

} else {
    logActivity($pdo, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown User', 'upload_rts', "Invalid RTS upload attempt - No file uploaded");
    $_SESSION["error"] = "No file uploaded.";
}

header("Location: rts_ui.php");
exit();
?>
