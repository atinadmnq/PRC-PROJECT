<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "prc_release_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


if (isset($_GET['id']) && isset($_GET['category'])) {
    $id = intval($_GET['id']);
    $category = $_GET['category'];


    if ($category === 'mailed') {
        $table = 'pdf_mailed';
    } elseif ($category === 'on_file') {
        $table = 'pdf_on_file';
    } else {
        die("Invalid category.");
    }

   
    $stmt = $conn->prepare("SELECT file_path, file_name FROM `$table` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
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
            echo "File not found on server.";
        }
    } else {
        echo "No record found.";
    }
} else {
    echo "Invalid request.";
}

$conn->close();
?>