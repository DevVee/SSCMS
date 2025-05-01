<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, 
               CONCAT(p.last_name, ', ', p.first_name, ' ', p.middle_name) AS patient_name 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        WHERE DATE(a.appointment_date) = CURDATE() AND LOWER(a.status) = 'approved' 
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'appointments' => $appointments]);
} catch (Exception $e) {
    error_log("[SSCMS Get Approved] Query error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch approved appointments']);
}
?>