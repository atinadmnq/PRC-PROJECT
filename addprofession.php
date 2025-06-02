<?php
// addprofession.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prc_release_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $newProfession = trim($_POST['profession_name'] ?? '');
    if ($newProfession === '') {
        echo json_encode(['status' => 'error', 'message' => 'Profession name cannot be empty']);
        exit;
    }

    $sql = "INSERT INTO professions (name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $newProfession);
        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;
            echo json_encode([
                'status' => 'success',
                'message' => "Profession '$newProfession' added successfully!",
                'data' => ['id' => $insertedId, 'name' => $newProfession]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement']);
    }
    $conn->close();
    exit;
}

// Fetch all professions for display
$professions = [];
$result = $conn->query("SELECT id, name FROM professions ORDER BY id DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $professions[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Add Profession</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        td i.fa-edit {
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Add New Profession</h2>

    <div id="alertBox" style="display: none;" class="alert" role="alert"></div>

    <form id="professionForm">
        <div class="mb-3">
            <label for="profession_name" class="form-label">Profession Name</label>
            <input type="text" class="form-control" id="profession_name" name="profession_name" placeholder="Enter profession name" required />
        </div>
        <button type="submit" class="btn btn-primary">Add Profession</button>
    </form>

    <hr />

    <h4 class="mt-4">Profession List</h4>
    <table class="table table-bordered table-striped mt-3" id="professionTable">
        <thead class="table-dark">
            <tr>
                <th>Profession</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($professions as $row): ?>
                <tr data-id="<?php echo $row['id']; ?>">
                    <td class="profession-name"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="dashboard.php" class="btn btn-link mt-3">Back to Dashboard</a>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>

<script>
$(document).ready(function () {
    $('#professionForm').on('submit', function (e) {
        e.preventDefault();
        const profession = $('#profession_name').val().trim();

        if (profession === '') {
            showAlert('Profession name is required.', 'danger');
            return;
        }

        $.post('addprofession.php', {
            profession_name: profession,
            ajax: 1
        }, function (response) {
            if (response.status === 'success') {
                showAlert(response.message, 'success');
                $('#professionForm')[0].reset();

                // Append new profession to table
                const newRow = `
                    <tr data-id="${response.data.id}">
                        <td class="profession-name">${response.data.name}</td>
                        <td><button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</button></td>
                    </tr>`;
                $('#professionTable tbody').prepend(newRow);
            } else {
                showAlert(response.message, 'danger');
            }
        }, 'json').fail(function () {
            showAlert('Request failed. Please try again.', 'danger');
        });
    });

    function showAlert(message, type) {
        $('#alertBox')
            .removeClass('alert-success alert-danger alert-info')
            .addClass('alert-' + type)
            .html(message)
            .slideDown();

        setTimeout(() => {
            $('#alertBox').slideUp();
        }, 4000);
    }
});
</script>
</body>
</html>
