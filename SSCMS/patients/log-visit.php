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

function sendSMS($number, $message) {
    $apiKey = '2840c5de6cdfbe118d100ad33fdc179b';
    $senderName = 'ICCBICLINIC';

    $url = 'https://api.semaphore.co/api/v4/messages';
    $data = [
        'apikey' => $apiKey,
        'number' => $number,
        'message' => $message,
        'sendername' => $senderName
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log("[SSCMS SMS Error] cURL Error: $error");
        $result = "cURL Error: $error";
    } else {
        error_log("[SSCMS SMS Response] Response: $response");
        $result = "SMS Response: $response";
    }

    curl_close($ch);
    return $result;
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

        // Get patient details for SMS
        $stmt = $conn->prepare("SELECT first_name, last_name, guardian_contact FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        $full_name = $patient['first_name'] . ' ' . $patient['last_name'];
        $guardian_number = $patient['guardian_contact'];

        // Log patient details for debugging
        error_log("[SSCMS Log New Patient] Patient Details: full_name=$full_name, guardian_number=" . ($guardian_number ?? 'NULL'));

        // Check if guardian_number is empty
        if (empty($guardian_number)) {
            error_log("[SSCMS Log New Patient] Warning: Guardian contact number is empty for patient_id=$patient_id");
            $_SESSION['sms_debug_message'] = "Failed to send SMS: Guardian contact number is empty.";
        } else {
            // Prepare SMS message
            $sms_message = "Good day! This is ICCBI CLINIC. We would like to inform you that your child, $full_name, visited the school clinic today at $visit_time on $visit_date for $reason. Rest assured they were properly attended to.";
            if ($reason === 'Other' && $other_reason) {
                $sms_message .= " (Details: $other_reason)";
            }
            $sms_response = sendSMS($guardian_number, $sms_message);
            error_log("[SSCMS Log New Patient] SMS sent to $guardian_number: $sms_message");
            $_SESSION['sms_debug_message'] = $sms_response;
        }

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