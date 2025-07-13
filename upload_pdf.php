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
<html>
<head>
    <title>Upload Scanned ROR PDFs</title>
    
</head>
<body>
    <h2>Upload PDFs</h2>

    <?php if (isset($uploadSuccess) && $uploadSuccess): ?>
        <p style="color: green;">Files uploaded successfully!</p>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <label>Select Year:</label>
        <input type="number" name="year" min="2000" max="2099" required><br><br>

        <input type="file" name="pdf_files[]" accept="application/pdf" multiple required><br><br>

        <input type="submit" name="submit" value="Upload PDFs">
    </form>

    <br>
    <form action="pdf_view.php" method="get">
        <button type="submit">📂 View Uploaded Files</button>
    </form>
</body>
</html>

<?php $conn->close(); ?>

