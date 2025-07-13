<?php
include 'admin_panel.php';
include 'db_connect.php';

$selectedYear = $_GET['year'] ?? null;

$years = $conn->query("SELECT DISTINCT year_folder FROM pdf_files ORDER BY year_folder DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Uploaded PDFs</title>
    
</head>
<body>
    <h2>Browse Uploaded PDFs by Year</h2>

    <form method="get" action="pdf_view.php">
        <label>Select Year:</label>
        <select name="year" onchange="this.form.submit()">
            <option value="">-- Select Year --</option>
            <?php while ($y = $years->fetch_assoc()): ?>
                <option value="<?= $y['year_folder'] ?>" <?= $selectedYear == $y['year_folder'] ? 'selected' : '' ?>>
                    <?= $y['year_folder'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php
    if ($selectedYear):
        $stmt = $conn->prepare("SELECT * FROM pdf_files WHERE year_folder = ? ORDER BY year_folder DESC");
        $stmt->bind_param("s", $selectedYear);
        $stmt->execute();
        $result = $stmt->get_result();
    ?>

        <h3>Files from Year <?= htmlspecialchars($selectedYear) ?></h3>
        <table border="1" cellpadding="10">
            <tr>
                <th>File Name</th>
                <th>Examination Year</th>
                <th>Download</th>
                <th>Action</th>
            </tr>

            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['file_name']) ?></td>
                        <td><?= $row['year_folder'] ?></td>
                        <td><a href="<?= htmlspecialchars($row['file_path']) ?>" download>Download</a></td>
                        <td>
                            <form method="post" action="delete_pdf.php" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="submit" value="Delete">
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No files found for this year.</td></tr>
            <?php endif; ?>

        </table>

    <?php endif; ?>

</body>
</html>

<?php $conn->close(); ?>