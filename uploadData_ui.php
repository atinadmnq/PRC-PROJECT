<?php
session_start();
include 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload ROR Excel File</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f8;
            padding: 20px;
        }

        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        input[type="file"] {
            padding: 8px;
        }

        button {
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        .note {
            margin-top: 10px;
            font-size: 14px;
            color: #555;
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .view-records-btn {
            display: block;
            width: 100%;
            margin-top: 20px;
            text-align: center;
            text-decoration: none;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .view-records-btn:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Upload ROR Excel File</h1>

        <!-- Show message if any -->
        <?php if (isset($_SESSION["message"])): ?>
            <div class="message success">
                <?php 
                    echo $_SESSION["message"]; 
                    unset($_SESSION["message"]);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION["error"])): ?>
            <div class="message error">
                <?php 
                    echo $_SESSION["error"]; 
                    unset($_SESSION["error"]);
                ?>
            </div>
        <?php endif; ?>

        <!-- Upload form -->
        <form id="uploadForm" action="upload_data.php" method="POST" enctype="multipart/form-data">
            <label for="excel_file">Select Excel File (.xlsx or .xls):</label>
            <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls" required>
            
            <button type="submit">Upload</button>
        </form>

        <div class="note">
            <p>Please make sure the file matches the required format:</p>
            <ul>
                <li>NO.</li>
                <li>NAME</li>
                <li>EXAMINATION</li>
                <li>EXAM DATE</li>
            </ul>
        </div>

        <!-- Button to go to records page -->
        <a href="viewData.php" class="view-records-btn">View Records</a>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('excel_file');
            
            if (!fileInput.value) {
                alert('Please select an Excel file to upload.');
                e.preventDefault();
                return;
            }
            
            const confirmed = confirm('Are you sure you want to upload this file?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    </script>

</body>
</html>
