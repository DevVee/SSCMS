<?php
session_start();
require_once 'config/database.php';

// Function to validate session
function isValidSession($conn, $user_id, $session_id) {
    try {
        $stmt = $conn->prepare("SELECT last_active FROM sessions WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$user_id, $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        return $session && (time() - strtotime($session['last_active']) < 3600); // 1-hour session timeout
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

// Check authentication
if (isset($_SESSION['user_id']) && isset($_SESSION['admin_category']) && isValidSession($conn, $_SESSION['user_id'], session_id())) {
    // Update session last_active
    try {
        $stmt = $conn->prepare("UPDATE sessions SET last_active = NOW() WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$_SESSION['user_id'], session_id()]);
        header('Location: /SSCMS/dashboard.php');
        exit;
    } catch (Exception $e) {
        error_log("Index session update error: " . $e->getMessage());
        session_unset();
        session_destroy();
    }
}

// If session is invalid or missing, redirect to login
error_log("Unauthorized access to index.php: invalid or no session");
header('Location: /SSCMS/login.php');
exit;
?>