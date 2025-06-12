<?php
include 'db_connect.php';

// Handle "Release" (Delete) action
if (isset($_POST['release_id'])) {
    $release_id = intval($_POST['release_id']);
    
    $stmt = $conn->prepare("DELETE FROM roravailable WHERE id = ?");
    $stmt->bind_param("i", $release_id);
    
    if ($stmt->execute()) {
        // Optional: add a message or redirect after successful delete
        header("Location: " . $_SERVER['PHP_SELF'] . "?examination=" . urlencode($_GET['examination'] ?? '') . "&search_name=" . urlencode($_GET['search_name'] ?? ''));
        exit;
    } else {
        echo "Error releasing record: " . $conn->error;
    }
    $stmt->close();
}

// Get all distinct examinations
$sql = "SELECT DISTINCT examination FROM roravailable ORDER BY upload_timestamp DESC";
$result = $conn->query($sql);

$examinations = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $examinations[] = $row['examination'];
    }
}

// Get count per examination
$sql_counts = "SELECT examination, COUNT(*) as count FROM roravailable GROUP BY examination";
$result_counts = $conn->query($sql_counts);

$exam_counts = [];
$total_count = 0;

if ($result_counts) {
    while ($row = $result_counts->fetch_assoc()) {
        $exam_counts[$row['examination']] = $row['count'];
        $total_count += $row['count'];
    }
}

// Read GET parameters safely
$exam = isset($_GET['examination']) ? trim($conn->real_escape_string($_GET['examination'])) : '';
$search_name = isset($_GET['search_name']) ? trim($conn->real_escape_string($_GET['search_name'])) : '';

// If examination selected, fetch its data with optional name filter
$data = [];
if ($exam !== '') {
    $sql_data = "SELECT id, name, examination, exam_date, upload_timestamp, status 
                 FROM roravailable 
                 WHERE LOWER(examination) = LOWER('$exam')";

    if ($search_name !== '') {
        $sql_data .= " AND name LIKE '%$search_name%'";
    }

    $sql_data .= " ORDER BY upload_timestamp DESC";

    $result_data = $conn->query($sql_data);

    if ($result_data) {
        while ($row = $result_data->fetch_assoc()) {
            $data[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>View Uploaded ROR Data</title>
    <style>
        body { font-family: "Century Gothic"; max-width: 900px; margin: 30px auto; }
        select, input[type=text] { padding: 8px; font-size: 16px; font-family: "Century Gothic"}
        select { width: 250px; font-family: "Century Gothic" }
        input[type=text] { width: 250px; }
        button { padding: 8px 15px; font-size: 16px; cursor: pointer; }
        form { margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #007BFF; color: white; }
        .summary { background-color: #f9f9f9; border: 1px solid #ccc; padding: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>View Uploaded ROR Data</h1>

    <!-- Display total counts here -->
    <div class="summary">
        <h2>Summary of Uploaded Data</h2>
        <p><strong>Total Records Uploaded (All Examinations):</strong> <?= $total_count ?></p>
        <ul>
            <?php foreach ($exam_counts as $exam_name => $count): ?>
                <li><strong><?= htmlspecialchars($exam_name) ?>:</strong> <?= $count ?> record(s)</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="get" action="" id="filterForm">
        <label for="examSelect">Select Examination:</label>
        <select name="examination" id="examSelect" required onchange="document.getElementById('filterForm').submit()">
            <option value="">Choose an examination</option>
            <?php foreach ($examinations as $examination): ?>
                <option value="<?= htmlspecialchars($examination) ?>" <?= ($exam === $examination) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($examination) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="searchName"; style=" margin-left:5px;">Search Name:</label>
        <input type="text" id="searchName" name="search_name" placeholder="Enter name to search" value="<?= htmlspecialchars($search_name) ?>"> 
        
        <button type="submit" style="margin-left:5px; font-family: Century Gothic;">Search</button>
    </form>

    <?php if (!empty($data)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Examination</th>
                    <th>Exam Date</th>
                    <th>Upload Timestamp</th>
                    <th>Status</th>
                    <th>Action</th> 
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['examination']) ?></td>
                        <td><?= htmlspecialchars($row['exam_date']) ?></td>
                        <td><?= htmlspecialchars(date("M-d-Y H:m:s", strtotime($row['upload_timestamp']))) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td>
                            <form method="post" action="" onsubmit="return confirm('Are you sure you want to release (delete) this record?');">
                                <input type="hidden" name="release_id" value="<?= htmlspecialchars($row['id']) ?>">
                                <button type="submit" style="font-family: Century Gothic;">Release</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($exam !== ''): ?>
        <p>No records found for examination: <?= htmlspecialchars($exam) ?></p>
    <?php endif; ?>
</body>
</html>
