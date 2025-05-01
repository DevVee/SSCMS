<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    $query = "
        SELECT 
            ml.id,
            m.name AS medicine_name,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            ml.quantity_used,
            ml.visit_date,
            ml.reason
        FROM medicine_logs ml
        JOIN medicines m ON ml.medicine_id = m.id
        JOIN patients p ON ml.patient_id = p.id
        ORDER BY ml.visit_date DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['data' => $logs]);
} catch (Exception $e) {
    error_log("[SSCMS Fetch Medicine Logs] Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to fetch medicine logs']);
}
?>