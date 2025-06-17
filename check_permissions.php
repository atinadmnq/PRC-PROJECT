<?php
// check_permissions.php - API endpoint to check user permissions
session_start();
header('Content-Type: application/json');

// Database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'prc_release_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_email = $_SESSION['email'] ?? '';

// Get current permissions
$stmt = $pdo->prepare("
    SELECT permission_type, granted_at 
    FROM user_permissions 
    WHERE user_email = ? AND status = 'active'
");
$stmt->execute([$user_email]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there are any recent permission changes
$last_check = $_SESSION['last_permission_check'] ?? 0;
$current_time = time();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as new_permissions 
    FROM user_permissions 
    WHERE user_email = ? AND status = 'active' AND granted_at > FROM_UNIXTIME(?)
");
$stmt->execute([$user_email, $last_check]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION['last_permission_check'] = $current_time;

echo json_encode([
    'permissions' => $permissions,
    'refresh_needed' => $result['new_permissions'] > 0,
    'timestamp' => $current_time
]);
?>