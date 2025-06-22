<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_release_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header("Location: index.php");
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $_SESSION['error'] = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($stmt->execute([$hashed_password, $user_id])) {
                $_SESSION['success'] = "Password changed successfully!";
                
                // Log the activity
                try {
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, user_name, action, description, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $user_id,
                        $user_data['full_name'],
                        'password_change',
                        $user_data['full_name'] . ' changed their password'
                    ]);
                } catch (PDOException $e) {
                    error_log("Activity log failed: " . $e->getMessage());
                }
            } else {
                $_SESSION['error'] = "Failed to update password. Please try again.";
            }
        } else {
            $_SESSION['error'] = "Current password is incorrect.";
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    if (empty($full_name) || empty($email)) {
        $_SESSION['error'] = "Please fill in all profile fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
    } else {
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Email address is already in use by another account.";
        } else {
            // Update profile
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            
            if ($stmt->execute([$full_name, $email, $user_id])) {
                $_SESSION['success'] = "Profile updated successfully!";
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $user_data['full_name'] = $full_name;
                $user_data['email'] = $email;
                
                // Log the activity
                try {
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, user_name, action, description, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $user_id,
                        $full_name,
                        'profile_update',
                        $full_name . ' updated their profile information'
                    ]);
                } catch (PDOException $e) {
                    error_log("Activity log failed: " . $e->getMessage());
                }
            } else {
                $_SESSION['error'] = "Failed to update profile. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - RILIS</title>
    <link rel="icon" type="image/x-icon" href="img/rilis-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   
     <?php
    // Load role-specific CSS
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') {
            echo '<link href="css/dashboard.css" rel="stylesheet">';
        } elseif ($_SESSION['role'] === 'staff') {
            echo '<link href="css/staff_dashboard.css" rel="stylesheet">';
        }
    }
    ?>
    <style>
        body {
            background: #f8f9fa;
            font-family: "Century Gothic";
            margin: 0;
            padding: 0;
        }
        
        .profile-card {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 2rem;
        }
        
        .settings-card .card-header {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 1.5rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, rgb(41, 63, 161) 0%, rgb(49, 124, 210) 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
    </style>
</head>
<body>
   
   <?php
    // IMPROVED CONDITIONAL PANEL INCLUDE
    if (isset($_SESSION['role'])) {
        $userRole = strtolower(trim($_SESSION['role']));
        
        if ($userRole === 'admin') {
            if (file_exists('admin_panel.php')) {
                include 'admin_panel.php';
            } else {
                echo "<!-- Admin panel file not found -->";
            }
        } elseif ($userRole === 'staff') {
            if (file_exists('staff_panel.php')) {
                include 'staff_panel.php';
            } else {
                echo "<!-- Staff panel file not found -->";
            }
        } else {
            echo "<!-- Unknown role: " . htmlspecialchars($_SESSION['role']) . " -->";
        }
    } else {
        echo "<!-- No role set in session -->";
        // Fallback - you might want to include a default panel or redirect
    }
    ?>


    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-cog me-3" id="fonty"></i>Account Settings
            </h1>
            <p class="text-muted">Manage your profile and security settings</p>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
      <!-- Profile Overview -->
        <div class="profile-card text-center">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h3 class="mb-1"><?php echo htmlspecialchars($user_data['full_name']); ?></h3>
            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($user_data['email']); ?></p>
            <small class="opacity-75">
                <?php 
                    // Display role based on user type
                    if (isset($user_data['role'])) {
                        switch(strtolower($user_data['role'])) {
                            case 'admin':
                                echo 'Administrator Account';
                                break;
                            case 'staff':
                                echo 'Staff Member';
                                break;
                            default:
                                echo ucfirst($user_data['role']) . ' Account';
                                break;
                        }
                    } elseif (isset($user_data['user_type'])) {
                        // Alternative field name for user type
                        switch(strtolower($user_data['user_type'])) {
                            case 'admin':
                                echo 'Administrator Account';
                                break;
                            case 'staff':
                                echo 'Staff Member';
                                break;
                            default:
                                echo ucfirst($user_data['user_type']) . ' Account';
                                break;
                        }
                    } else {
                        // Fallback if no role field exists
                        echo 'User Account';
                    }
                ?>
            </small>
        </div>
        
        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-6">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user me-2"></i>Profile Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">
                                    <i class="fas fa-user me-2"></i>Full Name
                                </label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="col-md-6">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-lock me-2"></i>Change Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="passwordForm">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">
                                    <i class="fas fa-key me-2"></i>Current Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye" id="current_password_icon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye" id="new_password_icon"></i>
                                    </button>
                                </div>
                                <div class="password-strength mt-2" id="passwordStrength"></div>
                                <small class="form-text text-muted">
                                    Must contain uppercase, lowercase, number and special character (min 8 chars)
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Confirm New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye" id="confirm_password_icon"></i>
                                    </button>
                                </div>
                                <small class="form-text" id="passwordMatch"></small>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="change_password" class="btn btn-primary" id="changePasswordBtn">
                                    <i class="fas fa-shield-alt me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            const isLongEnough = password.length >= 8;
            
            let strength = 0;
            if (hasUpper) strength++;
            if (hasLower) strength++;
            if (hasNumber) strength++;
            if (hasSpecial) strength++;
            if (isLongEnough) strength++;
            
            strengthBar.style.width = (strength * 20) + '%';
            
            if (strength < 3) {
                strengthBar.className = 'password-strength strength-weak';
            } else if (strength < 5) {
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'password-strength strength-strong';
            }
        });
        
        // Password match checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('changePasswordBtn');
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchText.textContent = 'Passwords match ✓';
                    matchText.className = 'form-text text-success';
                    submitBtn.disabled = false;
                } else {
                    matchText.textContent = 'Passwords do not match ✗';
                    matchText.className = 'form-text text-danger';
                    submitBtn.disabled = true;
                }
            } else {
                matchText.textContent = '';
                submitBtn.disabled = false;
            }
        }
        
        document.getElementById('new_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return false;
            }
            
            const hasUpper = /[A-Z]/.test(newPassword);
            const hasLower = /[a-z]/.test(newPassword);
            const hasNumber = /[0-9]/.test(newPassword);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);
            const isLongEnough = newPassword.length >= 8;
            
            if (!(hasUpper && hasLower && hasNumber && hasSpecial && isLongEnough)) {
                e.preventDefault();
                alert('Password must contain uppercase, lowercase, number and special character (minimum 8 characters).');
                return false;
            }
        });
    </script>
</body>
</html>