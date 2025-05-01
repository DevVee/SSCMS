<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $query = "SELECT id, name, quantity FROM medicines ORDER BY name ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log query and results for debugging
    error_log("Fetch medicines query: $query");
    error_log("Results count: " . count($medicines));

    echo json_encode(['data' => $medicines]);
} catch (Exception $e) {
    error_log("Fetch medicines error: " . $e->getMessage());
    echo json_encode(['data' => [], 'error' => 'Database error']);
}
?>