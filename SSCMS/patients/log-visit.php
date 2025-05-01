<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /SSCMS/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $other_reason = filter_input(INPUT_POST, 'other_reason', FILTER_SANITIZE_STRING);
    $took_medicine = filter_input(INPUT_POST, 'took_medicine', FILTER_SANITIZE_STRING);
    $medicine = filter_input(INPUT_POST, 'medicine', FILTER_SANITIZE_STRING);
    $medicine_quantity = filter_input(INPUT_POST, 'medicine_quantity', FILTER_VALIDATE_INT);
    $visit_time = filter_input(INPUT_POST, 'visit_time', FILTER_SANITIZE_STRING);
    $visit_date = filter_input(INPUT_POST, 'visit_date', FILTER_SANITIZE_STRING);

    // Log sanitized values
    error_log("[SSCMS Log New Patient] Sanitized: patient_id=$patient_id, reason=$reason, took_medicine=$took_medicine, medicine=" . ($medicine ?? 'NULL') . ", medicine_quantity=" . ($medicine_quantity ?? 'NULL') . ", visit_date=$visit_date, visit_time=$visit_time");

    // Validate inputs
    if (!$patient_id || !$reason || !$visit_time || !$visit_date) {
        error_log("[SSCMS Log New Patient] Validation failed: Missing required fields");
        $_SESSION['error_message'] = 'Required fields are missing.';
        header('Location: log-new-patient.php');
        exit;
    }

    if ($reason === 'Other' && empty($other_reason)) {
        error_log("[SSCMS Log New Patient] Validation failed: Other reason not specified");
        $_SESSION['error_message'] = 'Please specify the other reason.';
        header('Location: log-new-patient.php');
        exit;
    }

    $medicine_id = null;
    if ($took_medicine === 'Yes') {
        if (!$medicine || $medicine_quantity <= 0) {
            error_log("[SSCMS Log New Patient] Validation failed: Invalid or missing medicine=$medicine, medicine_quantity=$medicine_quantity");
            $_SESSION['error_message'] = 'Medicine and quantity are required when medicine is taken.';
            header('Location: log-new-patient.php');
            exit;
        }

        // Verify medicine quantity
        $stmt = $conn->prepare("SELECT id, quantity FROM medicines WHERE name = ?");
        $stmt->execute([$medicine]);
        $med = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$med) {
            error_log("[SSCMS Log New Patient] Validation failed: Medicine not found or out of stock: $medicine");
            $_SESSION['error_message'] = 'Selected medicine not found or out of stock.';
            header('Location: log-new-patient.php');
            exit;
        }
        if ($medicine_quantity > $med['quantity']) {
            error_log("[SSCMS Log New Patient] Validation failed: Quantity exceeds stock. Requested=$medicine_quantity, Available={$med['quantity']}");
            $_SESSION['error_message'] = 'Quantity exceeds available stock.';
            header('Location: log-new-patient.php');
            exit;
        }
        $medicine_id = $med['id'];
        error_log("[SSCMS Log New Patient] Medicine selected: id=$medicine_id, name=$medicine, quantity=$medicine_quantity");
    } else {
        $medicine = null;
        $medicine_quantity = null;
        $took_medicine = 'No';
        error_log("[SSCMS Log New Patient] No medicine taken: medicine_id set to NULL");
    }

    // Format visit time
    $formatted_visit_time = date('H:i:s', strtotime($visit_time));
    if (!$formatted_visit_time) {
        error_log("[SSCMS Log New Patient] Validation failed: Invalid visit time format: $visit_time");
        $_SESSION['error_message'] = 'Invalid visit time format.';
        header('Location: log-new-patient.php');
        exit;
    }

    try {
        $conn->beginTransaction();

        // Insert visit
        $stmt = $conn->prepare("
            INSERT INTO visits (patient_id, reason, other_reason, took_medicine, medicine_name, medicine_quantity, visit_time, visit_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $patient_id,
            $reason,
            $other_reason ?: null,
            $took_medicine,
            $took_medicine === 'Yes' ? $medicine : null,
            $took_medicine === 'Yes' ? $medicine_quantity : null,
            $formatted_visit_time,
            $visit_date
        ]);
        $visit_id = $conn->lastInsertId();
        error_log("[SSCMS Log New Patient] Visit inserted into visits: visit_id=$visit_id, patient_id=$patient_id, medicine_name=" . ($medicine ?? 'NULL'));

        // Update medicine inventory and log to medicine_logs
        if ($took_medicine === 'Yes') {
            // Update medicines
            $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$medicine_quantity, $medicine_id]);
            $affected_rows = $stmt->rowCount();
            if ($affected_rows === 0) {
                error_log("[SSCMS Log New Patient] Failed to update medicines: No rows affected for medicine_id=$medicine_id");
                throw new Exception('Failed to update medicine quantity.');
            }
            error_log("[SSCMS Log New Patient] Medicine quantity updated: medicine_id=$medicine_id, quantity_used=$medicine_quantity");

            // Insert into medicine_logs
            $stmt = $conn->prepare("
                INSERT INTO medicine_logs (medicine_id, patient_id, quantity_used, visit_date, reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$medicine_id, $patient_id, $medicine_quantity, "$visit_date $formatted_visit_time", $reason]);
            error_log("[SSCMS Log New Patient] Medicine logged in medicine_logs: medicine_id=$medicine_id, patient_id=$patient_id, quantity_used=$medicine_quantity, visit_date=$visit_date $formatted_visit_time, reason=$reason");
        }

        $conn->commit();
        $_SESSION['success_message'] = 'Visit logged successfully.';
        error_log("[SSCMS Log New Patient] Success: patient_id=$patient_id, reason=$reason, took_medicine=$took_medicine, medicine=" . ($medicine ?? 'NULL') . ", medicine_quantity=" . ($medicine_quantity ?? 'NULL'));
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error logging visit: ' . $e->getMessage();
        error_log("[SSCMS Log New Patient] Error: " . $e->getMessage() . " | Line: " . $e->getLine());
    }
}

header('Location: log-new-patient.php');
exit;
?>