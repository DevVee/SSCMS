<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get search term
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';

try {
    $query = "
        SELECT id, last_name, first_name, middle_name, category, gender, grade_year, program_section, guardian_contact
        FROM patients
    ";
    $params = [];
    
    if ($searchTerm) {
        $query .= " WHERE last_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR category LIKE ? OR gender LIKE ? OR grade_year LIKE ? OR program_section LIKE ? OR guardian_contact LIKE ?";
        $searchPattern = '%' . $searchTerm . '%';
        $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern];
    }
    
    $query .= " ORDER BY last_name, first_name, middle_name";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("[SSCMS Search Patients] Fetched " . count($patients) . " patients for search: '$searchTerm'");
    
    echo json_encode(['data' => $patients]);
} catch (Exception $e) {
    error_log("[SSCMS Search Patients] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>