<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// DB connection
include 'db_connect.php'; // Ensure this file contains your database connection logic

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['spreadsheet'])) {
    $targetFile = __DIR__ . '/uploads/' . basename($_FILES['spreadsheet']['name']);
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0777, true);

    if (move_uploaded_file($_FILES['spreadsheet']['tmp_name'], $targetFile)) {
        $spreadsheet = IOFactory::load($targetFile);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $examinationValue = null;

        // 🔍 Search all rows and columns for a cell labeled "EXAMINEES"
        foreach ($rows as $row) {
            foreach ($row as $colIndex => $cell) {
                if (strtoupper(trim($cell)) === 'EXAMINATION') {
                    $examinationValue = isset($row[$colIndex + 1]) ? trim($row[$colIndex + 1]) : null;
                    break 2;
                }
            }
        }

        // ✅ Insert into professions table as "name"
        if (!empty($examinationValue)) {
            $stmt = $pdo->prepare("INSERT INTO professions (name) VALUES (:val)");
            $stmt->execute(['val' => $examinationValue]);
            echo "<p style='color:green;'>Inserted '$examinationValue' into 'professions' table under 'name' column.</p>";
        } else {
            echo "<p style='color:red;'>Could not find a valid 'EXAMINEES' value.</p>";
        }
    } else {
        echo "<p style='color:red;'>File upload failed.</p>";
    }
}
?>

<!-- HTML Upload Form -->
<!DOCTYPE html>
<html>
<head><title>Upload Excel (Insert EXAMINEES into 'professions')</title></head>
<body>
    <h2>Upload Excel File</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="spreadsheet" accept=".xlsx,.xls" required>
        <button type="submit">Upload & Insert</button>
    </form>
</body>
</html>
