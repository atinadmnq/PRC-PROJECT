<?php
// activity_logger.php
// Include this file in your PHP scripts to log activities

/**
 * Logs an activity to the activity_log table.
 *
 * @param PDO $pdo Database connection
 * @param int|null $userId (optional) User ID — unused in table but kept for future compatibility
 * @param string $accountName Account name performing the action
 * @param string $activityType Type of activity (e.g., 'Release ROR', 'Upload ROR')
 * @param string $description Description of the activity
 * @param string $clientName (optional) Name of the client/examinee
 * @param string $fileName (optional) File name related to activity
 * @param int|null $releaseId (optional) Release ID
 * @return bool True on success, false on failure
 */
function logActivity($pdo, $userId, $accountName, $activityType, $description, $clientName = '', $fileName = '', $releaseId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log 
                (account_name, activity_type, description, client_name, file_name, release_id, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $accountName,
            $activityType,
            $description,
            $clientName,
            $fileName,
            $releaseId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

function logUserCreation($pdo, $userId, $accountName, $newFullName, $newEmail, $newRole) {
    $description = "Registered new user: $newFullName ($newEmail) as $newRole";
    return logActivity($pdo, $userId, $accountName, 'Create User', $description);
}

/**
 * Logs individual release activity
 */
function logReleaseActivity($pdo, $userId, $accountName, $clientName, $releaseType = 'individual', $additionalInfo = '', $releaseId = null) {
    $description = "Released Data ($releaseType)";
    if (!empty($additionalInfo)) {
        $description .= " - $additionalInfo";
    }
    return logActivity($pdo, $userId, $accountName, 'Release ROR', $description, $clientName, '', $releaseId);
}

/**
 * Logs bulk release summary with examination names
 */
function logBulkReleaseSummary($pdo, $userId, $accountName, $successCount, $failedCount, $names = [], $examinationName = '') {
    $description = "Bulk release completed - Success: $successCount, Failed: $failedCount";
    
    // Add examination name if provided
    if (!empty($examinationName)) {
        $description = "Bulk release for " . $examinationName . " - Success: $successCount, Failed: $failedCount";
    }
    
    if (!empty($names)) {
        $description .= " - Names: " . implode(", ", array_slice($names, 0, 10));
        if (count($names) > 10) {
            $description .= "...";
        }
    }
    return logActivity($pdo, $userId, $accountName, 'Bulk Release Summary', $description);
}

/**
 * Logs ROR file upload activity
 */
function logRORUpload($pdo, $userId, $accountName, $fileName = '', $recordCount = 0) {
    $description = "Uploaded ROR data";
    if (!empty($fileName)) {
        $description .= " - File: $fileName";
    }
    if ($recordCount > 0) {
        $description .= " ($recordCount records)";
    }
    return logActivity($pdo, $userId, $accountName, 'Upload ROR', $description, '', $fileName);
}

/**
 * Logs RTS file upload activity
 */
function logRTSUpload($pdo, $userId, $accountName, $fileName = '', $recordCount = 0) {
    $description = "Uploaded RTS data";
    if (!empty($fileName)) {
        $description .= " - File: $fileName";
    }
    if ($recordCount > 0) {
        $description .= " ($recordCount records)";
    }
    return logActivity($pdo, $userId, $accountName, 'Upload RTS', $description, '', $fileName);
}

/**
 * Logs table view activity (RTS/ROR)
 */
function logTableView($pdo, $userId, $accountName, $tableType = 'RTS') {
    $description = "Accessed $tableType table view";
    return logActivity($pdo, $userId, $accountName, 'View Table', $description);
}

/**
 * Logs login activity
 */
function logLoginActivity($pdo, $userId, $accountName, $success = true) {
    $activityType = $success ? 'Login' : 'Login Failed';
    $description = $success ? 'User logged in successfully' : 'Failed login attempt';
    return logActivity($pdo, $userId, $accountName, $activityType, $description);
}

/**
 * Logs logout activity
 */
function logLogoutActivity($pdo, $userId, $accountName) {
    $description = 'User logged out';
    return logActivity($pdo, $userId, $accountName, 'Logout', $description);
}

?>