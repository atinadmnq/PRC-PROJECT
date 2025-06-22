<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// DB connection
$host = 'localhost';
$db   = 'prc_release_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Get POST values
$exam = $_POST['exam'] ?? '';
$search = $_POST['search_name'] ?? '';

if (empty($exam)) {
    die("No examination selected.");
}

// Query data
$sql = "SELECT id, name, examination, exam_date FROM roravailable WHERE LOWER(examination) = LOWER(?)";
$params = [$exam];

if (!empty($search)) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY exam_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('ROR Export');

$sheet->fromArray(['ID', 'Name', 'Examination', 'Exam Date'], null, 'A1');

$row = 2;
foreach ($data as $d) {
    $sheet->setCellValue("A$row", $d['id']);
    $sheet->setCellValue("B$row", $d['name']);
    $sheet->setCellValue("C$row", $d['examination']);
    $sheet->setCellValue("D$row", $d['exam_date']);
    $row++;
}

// Download
$cleanExam = preg_replace('/[^a-zA-Z0-9_-]/', '_', $exam);
$filename = "ROR_Export_{$cleanExam}_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');


$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
