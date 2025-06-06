<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$uploadedFile = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['spreadsheet'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = basename($_FILES['spreadsheet']['name']);
    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['spreadsheet']['tmp_name'], $targetFile)) {
        $uploadedFile = $fileName;

        $spreadsheet = IOFactory::load($targetFile);
        $sheet = $spreadsheet->getActiveSheet();

        echo "<h2>Extracted Data from: <i>$fileName</i></h2><table border='1' cellpadding='5'>";
        foreach ($sheet->getRowIterator() as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false); // include empty cells for full row check

    $rowData = [];
    foreach ($cellIterator as $cell) {
        $value = $cell->getValue();
        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
            $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        $rowData[] = $value;
    }

    // Check if all values in the row are empty
    $isEmptyRow = true;
    foreach ($rowData as $val) {
        if (trim($val) !== '') {
            $isEmptyRow = false;
            break;
        }
    }

    if ($isEmptyRow) {
        continue; // Skip blank rows
    }

    // Output the row
    echo "<tr>";
    foreach ($rowData as $val) {
        echo "<td>" . htmlspecialchars($val) . "</td>";
    }
    echo "</tr>";
}

        echo "</table>";

        // Show delete button
        echo "<form method='post'>
                <input type='hidden' name='deleteFile' value='" . htmlspecialchars($fileName) . "'>
                <button type='submit'>Delete File</button>
              </form>";
    } else {
        echo "<p style='color:red;'>Failed to upload file.</p>";
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteFile'])) {
    $fileToDelete = __DIR__ . '/uploads/' . basename($_POST['deleteFile']);
    if (file_exists($fileToDelete)) {
        unlink($fileToDelete);
        echo "<p style='color:green;'>File deleted successfully.</p>";
    } else {
        echo "<p style='color:red;'>File not found.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload & Delete Excel</title>
</head>
<body>
    <h1>Upload Excel File</h1>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="spreadsheet" accept=".xlsx,.xls" required>
        <button type="submit">Upload and Read</button>
    </form>
</body>
</html>
