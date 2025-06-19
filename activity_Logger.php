<?php
// activity_logger.php
// Include this file in your other PHP files to log activities

// Function to log activities with enhanced details
function logActivity($pdo, $userId, $userName, $action, $description, $additionalData = []) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, account_name, user_name, activity_type, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $userId,
            $userName,
            $userName,
            $action,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

// Function to log ROR upload activity
function logRORUpload($pdo, $userId, $userName, $fileName, $recordCount = 0) {
    $description = "Uploaded ROR file: {$fileName}";
    if ($recordCount > 0) {
        $description .= " ({$recordCount} records processed)";
    }
    return logActivity($pdo, $userId, $userName, 'upload_ror', $description);
}

// Function to log RTS upload activity
function logRTSUpload($pdo, $userId, $userName, $fileName, $recordCount = 0) {
    $description = "Uploaded RTS file: {$fileName}";
    if ($recordCount > 0) {
        $description .= " ({$recordCount} records processed)";
    }
    return logActivity($pdo, $userId, $userName, 'upload_rts', $description);
}

// Function to log release activity with examinee name
function logReleaseActivity($pdo, $userId, $userName, $examineeName, $releaseType = 'individual', $additionalInfo = '') {
    $description = "Released results for examinee: {$examineeName}";
    if ($releaseType !== 'individual') {
        $description = "Released results ({$releaseType}) - {$examineeName}";
    }
    if (!empty($additionalInfo)) {
        $description .= " - {$additionalInfo}";
    }
    return logActivity($pdo, $userId, $userName, 'release', $description);
}

// Function to log bulk release activity
function logBulkReleaseActivity($pdo, $userId, $userName, $examineesCount, $releaseDetails = '') {
    $description = "Released results for {$examineesCount} examinees";
    if (!empty($releaseDetails)) {
        $description .= " - {$releaseDetails}";
    }
    return logActivity($pdo, $userId, $userName, 'release', $description);
}

// Function to log login activity
function logLoginActivity($pdo, $userId, $userName, $success = true) {
    if ($success) {
        $description = "User logged in successfully";
        return logActivity($pdo, $userId, $userName, 'login', $description);
    } else {
        $description = "Failed login attempt - Invalid password";
        return logActivity($pdo, null, $userName, 'login', $description);
    }
}

// Function to log logout activity
function logLogoutActivity($pdo, $userId, $userName) {
    $description = "User logged out successfully";
    return logActivity($pdo, $userId, $userName, 'logout', $description);
}

// Function to log user creation activity
function logUserCreation($pdo, $adminUserId, $adminUserName, $newUserName, $newUserEmail) {
    $description = "Created new user account: {$newUserName} ({$newUserEmail})";
    return logActivity($pdo, $adminUserId, $adminUserName, 'create', $description);
}

// Function to log user update activity
function logUserUpdate($pdo, $userId, $userName, $updatedFields) {
    $description = "Updated user profile";
    if (!empty($updatedFields)) {
        $description .= " - Modified: " . implode(', ', $updatedFields);
    }
    return logActivity($pdo, $userId, $userName, 'update', $description);
}

// Function to log user deletion activity
function logUserDeletion($pdo, $adminUserId, $adminUserName, $deletedUserName) {
    $description = "Deleted user account: {$deletedUserName}";
    return logActivity($pdo, $adminUserId, $adminUserName, 'delete', $description);
}

// Function to log data processing activity
function logDataProcessing($pdo, $userId, $userName, $processType, $details) {
    $description = "Data processing: {$processType} - {$details}";
    return logActivity($pdo, $userId, $userName, 'update', $description);
}

// Function to log system configuration changes
function logSystemConfiguration($pdo, $userId, $userName, $configType, $changes) {
    $description = "System configuration updated: {$configType} - {$changes}";
    return logActivity($pdo, $userId, $userName, 'update', $description);
}

// Function to log file operations
function logFileOperation($pdo, $userId, $userName, $operation, $fileName, $details = '') {
    $description = "File {$operation}: {$fileName}";
    if (!empty($details)) {
        $description .= " - {$details}";
    }
    
    $action = 'update';
    if ($operation === 'upload') {
        $action = 'create';
    } elseif ($operation === 'delete') {
        $action = 'delete';
    }
    
    return logActivity($pdo, $userId, $userName, $action, $description);
}

// Function to get recent activities for dashboard display
function getRecentActivities($pdo, $limit = 10, $userId = null) {
    try {
        $query = "
            SELECT
                al.*,
                COALESCE(al.user_name, al.account_name, 'Unknown User') as full_name,
                CASE 
                    WHEN al.action = 'login' THEN 'success'
                    WHEN al.action = 'logout' THEN 'danger'
                    WHEN al.action = 'upload_ror' THEN 'info'
                    WHEN al.action = 'upload_rts' THEN 'warning'
                    WHEN al.action = 'release' THEN 'primary'
                    WHEN al.action = 'create' THEN 'primary'
                    WHEN al.action = 'update' THEN 'warning'
                    WHEN al.action = 'delete' THEN 'danger'
                    ELSE 'secondary'
                END as badge_class
            FROM activity_log al
        ";
        
        if ($userId) {
            $query .= " WHERE al.user_id = ?";
        }
        
        $query .= " ORDER BY al.created_at DESC LIMIT ?";
        
        $stmt = $pdo->prepare($query);
        
        if ($userId) {
            $stmt->execute([$userId, $limit]);
        } else {
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get recent activities: " . $e->getMessage());
        return [];
    }
}

// Function to get activity statistics
function getActivityStats($pdo, $days = 30) {
    try {
        $stats = [];
        
        // Get activity counts by type for the last X days
        $stmt = $pdo->prepare("
            SELECT 
                action,
                COUNT(*) as count
            FROM activity_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action
            ORDER BY count DESC
        ");
        $stmt->execute([$days]);
        $stats['by_action'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get daily activity counts
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM activity_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        $stats['daily'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get most active users
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(user_name, account_name, 'Unknown User') as user_name,
                COUNT(*) as count
            FROM activity_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY COALESCE(user_name, account_name, 'Unknown User')
            ORDER BY count DESC
            LIMIT 5
        ");
        $stmt->execute([$days]);
        $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Failed to get activity stats: " . $e->getMessage());
        return [];
    }
}

// Function to clean old activity logs (optional - for maintenance)
function cleanOldActivityLogs($pdo, $daysToKeep = 90) {
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$daysToKeep]);
        
        $deletedRows = $stmt->rowCount();
        
        // Log the cleanup activity
        logActivity($pdo, null, 'System', 'delete', "Cleaned {$deletedRows} old activity log entries (older than {$daysToKeep} days)");
        
        return $deletedRows;
    } catch (PDOException $e) {
        error_log("Failed to clean old activity logs: " . $e->getMessage());
        return false;
    }
}

?>