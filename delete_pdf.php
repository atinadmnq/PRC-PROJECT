<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "prc_release_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['category'])) {
    $id = intval($_POST['id']);
    $category = $_POST['category'];


    if ($category === 'mailed') {
        $table = 'pdf_mailed';
    } elseif ($category === 'on_file') {
        $table = 'pdf_on_file';
    } else {
        die("Invalid category specified.");
    }


    $stmt = $conn->prepare("SELECT file_path, year_folder FROM `$table` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($filePath, $yearFolder);
        $stmt->fetch();
        $stmt->close();


        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }

        $delStmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
        $delStmt->bind_param("i", $id);
        $delStmt->execute();
        $delStmt->close();

        header("Location: pdf_view.php?year=" . urlencode($yearFolder) . "&category=" . urlencode($category));
        exit();
    } else {
        $stmt->close();
        echo "File not found in the database.";
    }
} else {
    echo "Invalid request.";
}

$conn->close();
?>
