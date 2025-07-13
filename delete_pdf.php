<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "prc_release_db"; 

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    
    $stmt = $conn->prepare("SELECT file_path, year_folder FROM pdf_files WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($filePath, $yearFolder);
    $stmt->fetch();
    $stmt->close();

   
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    
    $delStmt = $conn->prepare("DELETE FROM pdf_files WHERE id = ?");
    $delStmt->bind_param("i", $id);
    $delStmt->execute();
    $delStmt->close();

    
    header("Location: pdf_view.php?year=" . urlencode($yearFolder));
    exit();
}
?>
