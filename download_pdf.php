<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "prc_release_db"; 

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (isset($_GET['id'])) {
    $id = $_GET['id'];

  
    $stmt = $conn->prepare("SELECT file_path, file_name FROM pdf_files WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($filePath, $fileName);
    $stmt->fetch();
    $stmt->close();

    if (file_exists($filePath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        echo "File not found.";
    }
} else {
    echo "Invalid file ID.";
}
?>
