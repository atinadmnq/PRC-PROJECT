<?php
// Add this function to your index.php file

function logActivity($account_name, $activity_type, $description, $client_name = null, $file_name = null, $release_id = null, $ip_address = null) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "prc_release_db";
    
    try {
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return false;
        }
        
        // Get client IP if not provided
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
        
        $stmt = $conn->prepare("INSERT INTO activity_log (account_name, activity_type, description, client_name, file_name, release_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $conn->close();
            return false;
        }
        
        $stmt->bind_param("sssssss", $account_name, $activity_type, $description, $client_name, $file_name, $release_id, $ip_address);
        
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        
        return $result;
        
    } catch (Exception $e) {
        error_log("LogActivity error: " . $e->getMessage());
        return false;
    }
}

session_start();
$servername = "localhost"; $username = "root"; $password = ""; $dbname = "prc_release_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$error_message = ""; $success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']); $password = $_POST['password'];
    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, email, password, role, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email); $stmt->execute(); $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id']; 
                $_SESSION['email'] = $user['email']; 
                $_SESSION['role'] = $user['role']; 
                $_SESSION['logged_in'] = true;
                $_SESSION['account_name'] = $user['full_name'] ?? $user['email'];
                
                // Log successful login
                logActivity($_SESSION['account_name'], 'login', 'User logged in successfully');
                
                if ($user['role'] == 'admin') header("Location: dashboard.php");
                else if ($user['role'] == 'staff') header("Location: staff_dashboard.php");
                else header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Invalid email or password.";
                // Log failed login attempt
                logActivity($email, 'login', 'Failed login attempt - Invalid password');
            }
        } else {
            $error_message = "Invalid email or password.";
            // Log failed login attempt
            logActivity($email, 'login', 'Failed login attempt - User not found');
        }
        $stmt->close();
    } else $error_message = "Please fill in all fields.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RILIS - Secure Access</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="overlay"></div>
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-8 col-lg-6">
                    <div class="login-card">
                        <div class="card-header text-center">
                            <div class="logo-container">
                                <img style="width: 90px; height: 90px;" src="img/rilis-logo.png" alt="RILIS Logo">
                            </div>
                            <h2 class="card-title" style="color: #9EC6F3">RILIS</h2>
                            <p class="card-subtitle">Secure PRC Release Management</p>
                        </div>
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">Remember me</label>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100 mb-3"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
                        </form>
                        <div class="text-center mt-4">
                            <small class="text-muted"><i class="fas fa-shield-alt me-1"></i>Secure PRC Release Management System</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setupPasswordToggle(passwordId, toggleId) {
            const toggleBtn = document.getElementById(toggleId);
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    const password = document.getElementById(passwordId);
                    const icon = this.querySelector('i');
                    if (password.type === 'password') {
                        password.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
                    } else {
                        password.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
                    }
                });
            }
        }
        setupPasswordToggle('password', 'togglePassword');

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-info):not(.alert-success)');
            alerts.forEach(function(alert) {
                if (!alert.classList.contains('alert-info') && !alert.classList.contains('alert-success')) {
                    const bsAlert = new bootstrap.Alert(alert); bsAlert.close();
                }
            });
        }, 5000);
    </script>
</body>
</html>