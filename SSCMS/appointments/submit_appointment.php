<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$required_fields = ['patient_name', 'category', 'phone', 'appointee', 'appointment_date', 'appointment_time', 'reason'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
}

$patient_name = filter_var($_POST['patient_name'], FILTER_SANITIZE_STRING);
$category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
$phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
$appointee = filter_var($_POST['appointee'], FILTER_SANITIZE_STRING);
$appointment_date = filter_var($_POST['appointment_date'], FILTER_SANITIZE_STRING);
$appointment_time = filter_var($_POST['appointment_time'], FILTER_SANITIZE_STRING);
$reason = filter_var($_POST['reason'], FILTER_SANITIZE_STRING);

if (!preg_match('/^\d{10,11}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number must be 10 or 11 digits']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if (!in_array($appointee, ['Doctor', 'Nurse', 'Dentist'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointee']);
    exit;
}

$valid_categories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni', 'Non-Student'];
if (!in_array($category, $valid_categories)) {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit;
}

try {
    // Insert appointment
    $stmt = $conn->prepare("
        INSERT INTO appointments (patient_name, category, phone, appointment_date, appointment_time, reason, appointee, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$patient_name, $category, $phone, $appointment_date, $appointment_time, $reason, $appointee]);
    error_log("[SSCMS Submit Appointment] Appointment created for $patient_name ($category) on $appointment_date $appointment_time");

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("[SSCMS Submit Appointment] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>