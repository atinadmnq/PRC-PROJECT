<?php
session_start();

// Database connection (adjust credentials)
define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_release_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Include or define the logActivity function
function logActivity($username, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (username, action, details, timestamp) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $action, $details]);
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Log the dashboard access
logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'dashboard_access', 'Staff dashboard accessed');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle logout
if (isset($_GET['logout'])) {
    logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'logout', 'Staff user logged out');
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle permission requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_permission'])) {
    $staff_email = $_SESSION['email'];
    $action = $_POST['action'];
    $reason_field = $action === 'release' ? 'release_reason' : 'upload_reason';
    $reason = trim(strip_tags($_POST[$reason_field] ?? ''));

    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'security_violation', 'Invalid CSRF token in permission request');
        die("Invalid CSRF token.");
    }

    // Basic validation
    if (empty($reason)) {
        $_SESSION['error_message'] = "Please provide a valid reason.";
        logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'permission_request_failed', "Empty reason for {$action} request");
    } else {
        // Save to database with status 'pending'
        $stmt = $pdo->prepare("INSERT INTO permission_requests (email, action, reason, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$staff_email, $action, $reason]);

        $_SESSION['permission_requested'] = true;
        $_SESSION['request_message'] = "Permission request for " . ucfirst($action) . " has been sent to admin.";
        
        // Log the permission request
        logActivity($_SESSION['account_name'] ?? $_SESSION['email'], 'permission_request', "Requested {$action} permission: " . substr($reason, 0, 100));
    }

    header("Location: staff_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - PRC Release</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, rgb(134, 65, 244) 0%, rgb(66, 165, 245) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .sidebar.mobile-closed {
            transform: translateX(-100%);
        }
        .sidebar-header, .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            color: white;
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.5rem;
        }
        .nav-menu {
            padding: 20px 0;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: 0.3s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-right: 3px solid white;
        }
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 30px;
        }
        .content-section { display: none; }
        .content-section.active { display: block; }
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .permission-required {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand"><i class="fas fa-shield-alt me-2"></i>PRC Release - Staff</a>
        </div>
        <div class="user-info text-center">
            <div class="user-avatar"><i class="fas fa-user"></i></div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
            <small class="text-light">Staff Member</small>
        </div>
        <nav class="nav-menu">
            <ul class="list-unstyled">
                <li><button class="nav-link active" data-section="dashboard"><i class="fas fa-tachometer-alt"></i>&nbsp;Dashboard</button></li>
                <li><button class="nav-link" data-section="release"><i class="fas fa-unlock-alt"></i>&nbsp;Request Data Release</button></li>
                <li><button class="nav-link" data-section="upload"><i class="fas fa-upload"></i>&nbsp;Request File Upload</button></li>
                <li><a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i>&nbsp;Logout</a></li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (!empty($_SESSION['permission_requested'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['request_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['permission_requested'], $_SESSION['request_message']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section active">
            <h1 class="page-title"><i class="fas fa-tachometer-alt me-2"></i>Staff Dashboard</h1>
            <p class="text-muted">Request admin approval for data release or file uploads.</p>
            <div class="row">
                <div class="col-md-6">
                    <div class="card p-4">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-unlock-alt me-2"></i>Request Data Release</h5>
                            <div class="alert alert-warning mt-2">Admin approval required</div>
                            <button class="btn btn-primary mt-2" data-section="release"><i class="fas fa-paper-plane me-2"></i>Request</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-4">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-upload me-2"></i>Request File Upload</h5>
                            <div class="alert alert-warning mt-2">Admin approval required</div>
                            <button class="btn btn-outline-primary mt-2" data-section="upload"><i class="fas fa-upload me-2"></i>Request</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Release Request Section -->
        <div id="release" class="content-section">
            <h1 class="page-title"><i class="fas fa-unlock-alt me-2"></i>Request Data Release</h1>
            <div class="permission-required">
                <i class="fas fa-shield-alt fa-2x text-warning mb-3"></i>
                <h5>Admin Permission Required</h5>
                <p>Submit your reason for requesting data release below.</p>
            </div>
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="action" value="release">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="request_permission" value="1">
                    <div class="mb-3">
                        <label class="form-label">Reason for Data Release</label>
                        <textarea class="form-control" name="release_reason" rows="4" required placeholder="Please explain why you need to release data..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
                    <button type="button" class="btn btn-outline-secondary" data-section="dashboard"><i class="fas fa-times me-2"></i>Cancel</button>
                </form>
            </div>
        </div>

        <!-- Upload Request Section -->
        <div id="upload" class="content-section">
            <h1 class="page-title"><i class="fas fa-upload me-2"></i>Request File Upload</h1>
            <div class="permission-required">
                <i class="fas fa-shield-alt fa-2x text-warning mb-3"></i>
                <h5>Admin Permission Required</h5>
                <p>Submit your reason for uploading files below.</p>
            </div>
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="request_permission" value="1">
                    <div class="mb-3">
                        <label class="form-label">Reason for File Upload</label>
                        <textarea class="form-control" name="upload_reason" rows="4" required placeholder="Please explain what files you need to upload and why..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
                    <button type="button" class="btn btn-outline-secondary" data-section="dashboard"><i class="fas fa-times me-2"></i>Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toggle Script -->
    <script>
        function switchSection(section) {
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelector(`.nav-link[data-section="${section}"]`)?.classList.add('active');
            document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
            document.getElementById(section)?.classList.add('active');
        }

        document.querySelectorAll('[data-section]').forEach(el => {
            el.addEventListener('click', () => switchSection(el.getAttribute('data-section')));
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>