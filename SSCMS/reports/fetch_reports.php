<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Sanitize and validate inputs
    $student = filter_input(INPUT_GET, 'student', FILTER_SANITIZE_STRING) ?? '';
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?? '';
    $time = filter_input(INPUT_GET, 'time', FILTER_SANITIZE_STRING) ?? '';

    // Validate time format (HH:MM)
    if ($time && !preg_match('/^\d{2}:\d{2}$/', $time)) {
        throw new Exception('Invalid time format');
    }

    // Build query
    $query = "
        SELECT 
            p.last_name,
            p.first_name,
            p.category,
            COALESCE(v.other_reason, v.reason) AS reason,
            v.visit_date,
            TIME_FORMAT(v.visit_time, '%h:%i %p') AS visit_time,
            v.medicine_name,
            v.medicine_quantity
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE 1=1
    ";
    $params = [];

    if ($student) {
        $query .= " AND (p.last_name LIKE ? OR p.first_name LIKE ? OR p.middle_name LIKE ?)";
        $params[] = "%$student%";
        $params[] = "%$student%";
        $params[] = "%$student%";
    }

    if ($date) {
        $query .= " AND v.visit_date = ?";
        $params[] = $date;
    }

    if ($time) {
        $query .= " AND v.visit_time = ?";
        $params[] = "$time:00";
    }

    $query .= " ORDER BY v.visit_date DESC, v.visit_time DESC";

    // Prepare and execute query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    if ($params) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log query and results for debugging
    error_log("Fetch reports query: $query");
    error_log("Parameters: " . json_encode($params));
    error_log("Results count: " . count($reports));

    echo json_encode(['data' => $reports]);
} catch (Exception $e) {
    error_log("Fetch reports error: " . $e->getMessage());
    echo json_encode(['data' => [], 'error' => 'Database error: ' . $e->getMessage()]);
}
?>