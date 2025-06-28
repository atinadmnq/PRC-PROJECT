<?php
session_start();
require 'vendor/autoload.php';
require_once 'activity_logger.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// DB connection
$host = 'localhost';
$db   = 'prc_release_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log database connection failure
    error_log("Export DB connection failed: " . $e->getMessage());
    die("DB connection failed: " . $e->getMessage());
}

// Get user information for logging
$accountName = $_SESSION['account_name'] ?? $_SESSION['email'] ?? $_SESSION['full_name'] ?? 'Unknown User';
$userId = $_SESSION['user_id'] ?? null;

// Get user's full name if not already set
if (!isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['full_name'] = $user['full_name'];
            $accountName = $user['full_name'];
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch user details: " . $e->getMessage());
    }
}

// Get POST values
$exam = $_POST['exam'] ?? '';
$search = $_POST['search_name'] ?? '';

// Validate input
if (empty($exam)) {
    // Log invalid export attempt
    logActivity(
        $pdo,
        $userId,
        $accountName,
        'export_failed',
        "Export attempt failed - No examination selected"
    );
    die("No examination selected.");
}

try {
    // Query data
    $sql = "SELECT id, name, examination, exam_date, upload_timestamp, status FROM rts_data_onhold WHERE LOWER(examination) = LOWER(?)";
    $params = [$exam];
    
    if (!empty($search)) {
        $sql .= " AND name LIKE ?";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY exam_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $record_count = count($data);
    
    if ($record_count === 0) {
        logActivity(
            $pdo,
            $userId,
            $accountName,
            'export_no_data',
            "Export cancelled - No data found for examination: {$exam}" . (!empty($search) ? ", Search: {$search}" : "")
        );
        die("No data found for the selected criteria.");
    }

    // Create Excel file
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ROR Export');
    
    // Set headers with additional columns
    $headers = ['ID', 'Name', 'Examination', 'Exam Date'];
    $sheet->fromArray($headers, null, 'A1');
    
    // Style the header row
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E2E8F0']
        ]
    ];
    $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
    
    // Add data rows
    $row = 2;
    foreach ($data as $d) {
        $sheet->setCellValue("A$row", $d['id']);
        $sheet->setCellValue("B$row", $d['name']);
        $sheet->setCellValue("C$row", $d['examination']);
        $sheet->setCellValue("D$row", $d['exam_date']);
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Generate filename
    $cleanExam = preg_replace('/[^a-zA-Z0-9_-]/', '_', $exam);
    $filename = "RTS_Export_{$cleanExam}_" . date('Ymd_His') . ".xlsx";
    
    // Create Excel writer
    $writer = new Xlsx($spreadsheet);
    
    // Log successful export with simplified message
    logActivity(
        $pdo,
        $userId,
        $accountName,
        'export_completed',
        "Exported RTS data for examination: {$exam} - Total records: {$record_count}"
    );
    
    // Send headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Output the file
    $writer->save('php://output');
    
    exit;

} catch (Exception $e) {
    // Log export failure
    logActivity(
        $pdo,
        $userId,
        $accountName,
        'export_failed',
        "Export failed for examination: {$exam} - Error: " . $e->getMessage()
    );
    
    // Also log to PHP error log
    error_log("ROR Export failed: " . $e->getMessage());
    
    // Redirect back with error message
    session_start();
    $_SESSION['release_message'] = "Export failed: " . $e->getMessage();
    $_SESSION['release_status'] = 'error';
    
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'viewData.php'));
    exit;
}
?>