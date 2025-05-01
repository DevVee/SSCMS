<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'], $_POST['status'], $_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
$status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);

if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    error_log("[SSCMS Update Appointment] Appointment ID $id updated to $status");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("[SSCMS Update Appointment] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>