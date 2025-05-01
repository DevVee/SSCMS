<?php
session_start();
require_once 'config/database.php';

// Check authentication
if (isset($_SESSION['user_id']) && isset($_SESSION['admin_category'])) {
    // Update session last_active
    try {
        $stmt = $conn->prepare("UPDATE sessions SET last_active = NOW() WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$_SESSION['user_id'], session_id()]);
    } catch (Exception $e) {
        error_log("Index session update error: " . $e->getMessage());
    }
    header('Location: /SSCMS/dashboard.php');
    exit;
} else {
    error_log("Unauthorized access to index.php: no session");
    header('Location: /SSCMS/login.php');
    exit;
}
?>