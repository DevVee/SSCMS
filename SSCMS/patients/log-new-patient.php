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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Log raw POST data for debugging
        error_log("[SSCMS Log Visit] Raw POST data: " . json_encode($_POST));

        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
        $other_reason = filter_input(INPUT_POST, 'other_reason', FILTER_SANITIZE_STRING);
        $took_medicine = filter_input(INPUT_POST, 'took_medicine', FILTER_SANITIZE_STRING);
        $medicine = filter_input(INPUT_POST, 'medicine', FILTER_SANITIZE_STRING);
        $medicine_quantity = filter_input(INPUT_POST, 'medicine_quantity', FILTER_VALIDATE_INT);
        $visit_date = filter_input(INPUT_POST, 'visit_date', FILTER_SANITIZE_STRING);
        $visit_time = filter_input(INPUT_POST, 'visit_time', FILTER_SANITIZE_STRING);

        // Log sanitized values
        error_log("[SSCMS Log Visit] Sanitized: patient_id=$patient_id, reason=$reason, took_medicine=$took_medicine, medicine=" . ($medicine ?? 'NULL') . ", medicine_quantity=" . ($medicine_quantity ?? 'NULL') . ", visit_date=$visit_date, visit_time=$visit_time");

        // Validate inputs
        if (!$patient_id || !$reason || !$visit_date || !$visit_time) {
            error_log("[SSCMS Log Visit] Validation failed: Missing required fields");
            throw new Exception('Required fields are missing.');
        }
        if ($reason === 'Other' && empty($other_reason)) {
            error_log("[SSCMS Log Visit] Validation failed: Other reason not specified");
            throw new Exception('Please specify the other reason.');
        }
        if (!preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9] (AM|PM)$/i', $visit_time)) {
            error_log("[SSCMS Log Visit] Validation failed: Invalid visit time format: $visit_time");
            throw new Exception('Invalid visit time format.');
        }

        $medicine_name = null;
        if ($took_medicine === 'Yes') {
            if (!$medicine || $medicine_quantity <= 0) {
                error_log("[SSCMS Log Visit] Validation failed: Invalid or missing medicine=$medicine, medicine_quantity=$medicine_quantity");
                throw new Exception('Medicine and quantity are required when medicine is taken.');
            }
            // Verify medicine quantity
            $stmt = $conn->prepare("SELECT name, quantity FROM medicines WHERE name = ? AND quantity > 0");
            $stmt->execute([$medicine]);
            $med = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$med) {
                error_log("[SSCMS Log Visit] Validation failed: Medicine not found or out of stock: $medicine");
                throw new Exception('Selected medicine not found or out of stock.');
            }
            if ($medicine_quantity > $med['quantity']) {
                error_log("[SSCMS Log Visit] Validation failed: Quantity exceeds stock. Requested=$medicine_quantity, Available={$med['quantity']}");
                throw new Exception('Quantity exceeds available stock.');
            }
            $medicine_name = $med['name'];
            error_log("[SSCMS Log Visit] Medicine selected: name=$medicine_name, quantity=$medicine_quantity");
        } else {
            $medicine = null;
            $medicine_quantity = null;
            $took_medicine = 'No';
            error_log("[SSCMS Log Visit] No medicine taken: medicine_name set to NULL");
        }

        // Format visit time
        $formatted_visit_time = date('H:i:s', strtotime($visit_time));
        if (!$formatted_visit_time) {
            error_log("[SSCMS Log Visit] Validation failed: Invalid visit time format: $visit_time");
            throw new Exception('Invalid visit time format.');
        }

        // Insert into visits
        $stmt = $conn->prepare("
            INSERT INTO visits (patient_id, reason, other_reason, took_medicine, medicine_name, medicine_quantity, visit_time, visit_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $patient_id,
            $reason,
            $other_reason ?: null,
            $took_medicine,
            $medicine_name,
            $medicine_quantity,
            $formatted_visit_time,
            $visit_date
        ]);
        $visit_id = $conn->lastInsertId();
        error_log("[SSCMS Log Visit] Visit inserted into visits: visit_id=$visit_id, patient_id=$patient_id, medicine_name=" . ($medicine_name ?? 'NULL'));

        // Get patient details for SMS
        $stmt = $conn->prepare("SELECT first_name, last_name, guardian_contact FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        $full_name = $patient['first_name'] . ' ' . $patient['last_name'];
        $guardian_number = $patient['guardian_contact'];

        // Log patient details for debugging
        error_log("[SSCMS Log Visit] Patient Details: full_name=$full_name, guardian_number=" . ($guardian_number ?? 'NULL'));

        // Check if guardian_number is empty
        if (empty($guardian_number)) {
            error_log("[SSCMS Log Visit] Warning: Guardian contact number is empty for patient_id=$patient_id");
            $_SESSION['sms_debug_message'] = "Failed to send SMS: Guardian contact number is empty.";
        } else {
            // Prepare SMS message
            $sms_message = "Good day! This is ICCBI CLINIC. We would like to inform you that your child, $full_name, visited the school clinic today at $visit_time on $visit_date for $reason. Rest assured they were properly attended to.";
            if ($reason === 'Other' && $other_reason) {
                $sms_message .= " (Details: $other_reason)";
            }
            $sms_response = sendSMS($guardian_number, $sms_message);
            error_log("[SSCMS Log Visit] SMS sent to $guardian_number: $sms_message");
            $_SESSION['sms_debug_message'] = $sms_response;
        }

        // Update medicine inventory and log to medicine_logs
        if ($took_medicine === 'Yes') {
            // Update medicines
            $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE name = ?");
            $stmt->execute([$medicine_quantity, $medicine]);
            $affected_rows = $stmt->rowCount();
            if ($affected_rows === 0) {
                error_log("[SSCMS Log Visit] Failed to update medicines: No rows affected for medicine=$medicine");
                throw new Exception('Failed to update medicine quantity.');
            }
            error_log("[SSCMS Log Visit] Medicine quantity updated: medicine=$medicine, quantity_used=$medicine_quantity");

            // Insert into medicine_logs
            $stmt = $conn->prepare("
                INSERT INTO medicine_logs (medicine_name, quantity_used, visit_id, patient_id, log_date)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$medicine_name, $medicine_quantity, $visit_id, $patient_id]);
            error_log("[SSCMS Log Visit] Medicine logged in medicine_logs: medicine_name=$medicine_name, quantity_used=$medicine_quantity, visit_id=$visit_id, patient_id=$patient_id");
        }

        $conn->commit();
        $_SESSION['success_message'] = 'Visit logged successfully!';
        error_log("[SSCMS Log Visit] Success: patient_id=$patient_id, reason=$reason, took_medicine=$took_medicine, medicine=" . ($medicine ?? 'NULL') . ", medicine_quantity=" . ($medicine_quantity ?? 'NULL'));
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        error_log("[SSCMS Log Visit] Error: " . $e->getMessage() . " | Line: " . $e->getLine());
    }
    header('Location: log-visit.php' . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit;
}

$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Log New Patient Visit">
    <meta name="author" content="ICCB">
    <title>Log New Patient Visit - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --secondary: #4b5563;
            --secondary-dark: #374151;
            --background: #f3f4f6;
            --card-bg: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border: #d1d5db;
            --success: #059669;
            --success-dark: #047857;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --warning: #d97706;
            --warning-dark: #b45309;
            --purple: #7c3aed;
            --purple-dark: #6d28d9;
            --bscs-maroon: #800000;
            --sidebar-width: 200px;
            --sidebar-collapsed-width: 50px;
            --header-height: 50px;
            --transition-speed: 0.2s;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            overflow-x: hidden;
            font-size: 0.85rem;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.25rem;
            padding-top: calc(var(--header-height) + 0.75rem);
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1440px;
            padding: 0 1rem;
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .dashboard-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }

        .dashboard-title i {
            color: var(--primary);
            font-size: 1.1rem;
            margin-right: 0.5rem;
            background-color: #e0f2fe;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-breadcrumb {
            padding: 0.5rem 1rem;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            font-size: 0.8rem;
        }

        .custom-breadcrumb .breadcrumb-item {
            color: var(--text-secondary);
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 600;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: none;
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .card-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1rem;
            font-weight: 600;
            border-radius: 12px 12px 0 0;
        }

        .table {
            color: var(--text-primary);
            font-size: 0.75rem;
        }

        .table th, .table td {
            padding: 0.4rem;
            border-color: var(--border);
            vertical-align: middle;
        }

        .table th {
            font-weight: 600;
            background: #f9fafb;
            color: var(--text-primary);
        }

        .btn {
            border-radius: 6px;
            padding: 0.4rem 0.9rem;
            font-weight: 500;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            transition: all var(--transition-speed);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid var(--border);
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            height: 38px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
        }

        .form-control:disabled {
            background-color: #f9fafb;
            opacity: 0.7;
        }

        .form-control.is-invalid, .form-select.is-invalid {
            border-color: var(--danger);
        }

        .form-label {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.4rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-text {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .modal-content {
            border-radius: 10px;
            background: var(--card-bg);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
            background-color: var(--bscs-maroon);
            color: white;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-lg {
            max-width: 600px;
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1050;
        }

        .clinic-footer {
            margin-top: 1.5rem;
            padding: 1rem 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
            border-top: 1px solid var(--border);
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        @media (max-width: 992px) {
            :root {
                --sidebar-width: var(--sidebar-collapsed-width);
            }
            .content {
                margin-left: var(--sidebar-width);
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 0.75rem;
                padding-top: calc(var(--header-height) + 0.5rem);
            }
            .modal-lg {
                max-width: 90%;
            }
            .table {
                font-size: 0.7rem;
            }
            .table th, .table td {
                padding: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Dashboard Header -->
                <div class="dashboard-header fade-in">
                    <h1 class="dashboard-title">
                        <i class="fas fa-user-plus"></i>
                        Log New Patient Visit
                    </h1>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Log New Patient Visit</li>
                    </ol>
                </nav>

                <!-- Toast Container -->
                <div class="toast-container">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sms_debug_message'])): ?>
                        <div class="toast align-items-center text-bg-warning border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($_SESSION['sms_debug_message']) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['sms_debug_message']); ?>
                    <?php endif; ?>
                </div>

                <!-- Search Card -->
                <div class="card fade-in">
                    <div class="card-header">
                        <i class="fas fa-search me-2"></i>Search Patients
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-search text-secondary"></i>
                                </span>
                                <input type="text" id="searchPatient" class="form-control" placeholder="Search by name, category, gender, grade/year, or program/section..." value="<?= htmlspecialchars($searchTerm) ?>">
                                <button id="searchBtn" class="btn btn-primary" disabled>
                                    <i class="fas fa-search me-1"></i>Search
                                    <span id="searchSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="patientTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Category</th>
                                        <th>Gender</th>
                                        <th>Grade/Year</th>
                                        <th>Program/Section</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Log Visit Modal -->
        <div class="modal fade" id="logVisitModal" tabindex="-1" aria-labelledby="logVisitModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="studentName">Log Patient Visit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="logVisitForm" action="log-visit.php" method="POST">
                            <input type="hidden" name="patient_id" id="patient_id">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reason" class="form-label">Reason for Visit <span class="text-danger">*</span></label>
                                        <select name="reason" id="reason" class="form-select" onchange="checkOtherReason()" required>
                                            <option value="">Select Reason</option>
                                            <option value="Headache">Headache</option>
                                            <option value="Stomach Ache">Stomach Ache</option>
                                            <option value="Fever">Fever</option>
                                            <option value="Cold/Flu">Cold/Flu</option>
                                            <option value="Cough">Cough</option>
                                            <option value="Dizziness">Dizziness</option>
                                            <option value="Nausea">Nausea</option>
                                            <option value="Vomiting">Vomiting</option>
                                            <option value="Diarrhea">Diarrhea</option>
                                            <option value="Allergy">Allergy</option>
                                            <option value="Skin Rash">Skin Rash</option>
                                            <option value="Injury (Wound/Bruise)">Injury (Wound/Bruise)</option>
                                            <option value="Sprain/Fracture">Sprain/Fracture</option>
                                            <option value="High Blood Pressure">High Blood Pressure</option>
                                            <option value="Low Blood Pressure">Low Blood Pressure</option>
                                            <option value="Menstrual Pain">Menstrual Pain</option>
                                            <option value="Eye Irritation">Eye Irritation</option>
                                            <option value="Ear Pain">Ear Pain</option>
                                            <option value="Dental Pain">Dental Pain</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <div class="form-text">Select the reason for the patient's visit</div>
                                    </div>
                                </div>
                                <div class="col-md-6" id="otherReasonDiv" style="display: none;">
                                    <div class="form-group">
                                        <label for="other_reason" class="form-label">Specify Other Reason</label>
                                        <input type="text" class="form-control" name="other_reason" id="other_reason">
                                        <div class="form-text">Enter details for other reason</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Did the patient take medicine? <span class="text-danger">*</span></label><br>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="took_medicine" id="took_medicine_no" value="No" onclick="toggleMedicineOptions(false)" checked>
                                            <label class="form-check-label" for="took_medicine_no">No</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="took_medicine" id="took_medicine_yes" value="Yes" onclick="toggleMedicineOptions(true)">
                                            <label class="form-check-label" for="took_medicine_yes">Yes</label>
                                        </div>
                                        <div class="form-text">Indicate if the patient took any medicine</div>
                                    </div>
                                </div>
                                <div class="col-md-6" id="medicineOptions" style="display: none;">
                                    <div class="form-group">
                                        <label for="medicine" class="form-label">Select Medicine <span class="text-danger">*</span></label>
                                        <select name="medicine" id="medicine" class="form-select" onchange="updateMedicineQuantity()">
                                            <option value="">-- Select Medicine --</option>
                                            <?php
                                            $medicines = $conn->query("SELECT name, quantity FROM medicines WHERE quantity > 0 ORDER BY name ASC");
                                            while ($med = $medicines->fetch(PDO::FETCH_ASSOC)):
                                            ?>
                                                <option value="<?= htmlspecialchars($med['name']) ?>" data-quantity="<?= $med['quantity'] ?>">
                                                    <?= htmlspecialchars($med['name']) . " (Available: " . $med['quantity'] . ")" ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="form-text">Select the medicine taken</div>
                                    </div>
                                    <div class="form-group">
                                        <label for="medicine_quantity" class="form-label">Quantity Taken <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="medicine_quantity" id="medicine_quantity" min="1" placeholder="Enter quantity" onchange="validateQuantity()">
                                        <div id="quantityWarning" class="text-danger small mt-1" style="display: none;">
                                            Quantity exceeds available stock!
                                        </div>
                                        <div class="form-text">Enter the quantity of medicine taken</div>
                                    </div>
                                </div>
                                <?php
                                date_default_timezone_set('Asia/Manila');
                                $display_date = date('l, F j, Y');
                                $save_date = date('Y-m-d');
                                ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="visit_time" class="form-label">Visit Time <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="visit_time" value="<?= date('h:i A') ?>" readonly>
                                        <div class="form-text">Current visit time</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="visit_date" class="form-label">Visit Date <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" value="<?= $display_date ?>" readonly>
                                        <input type="hidden" name="visit_date" value="<?= $save_date ?>">
                                        <div class="form-text">Current visit date</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="submitVisit">
                                    <i class="fas fa-save"></i> Log Visit
                                    <span id="logSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <footer class="clinic-footer fade-in">
            <div class="container-fluid">
                <p class="mb-0">Clinic Management System © 2025 ICCB. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        $(document).ready(function() {
            console.log('[SSCMS Log Visit] Initialized');

            // Initialize DataTable
            const patientTable = $('#patientTable').DataTable({
                serverSide: true,
                processing: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                language: {
                    search: "",
                    searchPlaceholder: "Search patients...",
                    processing: '<i class="fas fa-spinner fa-spin"></i> Loading...'
                },
                columnDefs: [{ orderable: false, targets: 7 }],
                ajax: {
                    url: 'search_patients.php',
                    type: 'GET',
                    data: function(d) {
                        d.search = $('#searchPatient').val().trim();
                        console.log('[SSCMS Log Visit] AJAX data:', d);
                    },
                    dataSrc: function(json) {
                        console.log('[SSCMS Log Visit] AJAX response:', json);
                        if (!json || !json.data) {
                            console.error('[SSCMS Log Visit] Invalid JSON response:', json);
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Log Visit] AJAX error:', error, thrown, xhr.responseText);
                        alert('Error fetching patients. Check console for details.');
                    }
                },
                columns: [
                    { data: 'last_name' },
                    { data: 'first_name' },
                    { data: 'middle_name', render: function(data) { return data || 'N/A'; } },
                    { data: 'category' },
                    { data: 'gender' },
                    { data: 'grade_year', render: function(data) { return data || 'N/A'; } },
                    { data: 'program_section', render: function(data) { return data || 'N/A'; } },
                    {
                        data: null,
                        render: function(data) {
                            return `
                                <button class="btn btn-primary btn-sm log-visit-btn" data-patient='${JSON.stringify(data)}'>
                                    <i class="fas fa-user-plus"></i> Log Visit
                                </button>
                            `;
                        }
                    }
                ]
            });

            // Enable/disable search button
            $('#searchPatient').on('input', function() {
                const searchTerm = $(this).val().trim();
                $('#searchBtn').prop('disabled', !searchTerm);
            });

            // Search on keyup (debounced)
            $('#searchPatient').on('keyup', debounce(function() {
                const searchTerm = $(this).val().trim();
                console.log('[SSCMS Log Visit] Keyup search:', searchTerm);
                $('#searchSpinner').removeClass('d-none');
                $('#searchBtn').prop('disabled', true);
                patientTable.ajax.reload(function() {
                    $('#searchSpinner').addClass('d-none');
                    $('#searchBtn').prop('disabled', !searchTerm);
                });
                // Update URL
                history.pushState({}, '', `?search=${encodeURIComponent(searchTerm)}`);
            }, 300));

            // Search button click
            $('#searchBtn').on('click', function() {
                console.log('[SSCMS Log Visit] Search button clicked');
                $('#searchSpinner').removeClass('d-none');
                patientTable.ajax.reload(function() {
                    $('#searchSpinner').addClass('d-none');
                });
            });

            // Enter key triggers search
            $('#searchPatient').on('keypress', function(e) {
                if (e.which === 13 && !$('#searchBtn').prop('disabled')) {
                    console.log('[SSCMS Log Visit] Enter key pressed');
                    $('#searchBtn').click();
                }
            });

            // Handle pre-filled search from URL
            const initialSearch = '<?= htmlspecialchars($searchTerm) ?>';
            if (initialSearch) {
                $('#searchPatient').val(initialSearch);
                $('#searchBtn').prop('disabled', false);
                $('#searchSpinner').removeClass('d-none');
                patientTable.ajax.reload(function() {
                    $('#searchSpinner').addClass('d-none');
                });
            }

            // Log visit button click
            $('#patientTable').on('click', '.log-visit-btn', function() {
                const patient = $(this).data('patient');
                console.log('[SSCMS Log Visit] Logging visit for patient:', patient);
                $('#patient_id').val(patient.id);
                $('#studentName').text(`Log Visit for ${patient.first_name} ${patient.last_name}`);
                $('#logVisitForm')[0].reset();
                $('#reason').val('');
                $('#otherReasonDiv').hide();
                $('#other_reason').prop('required', false).val('');
                $('#medicineOptions').hide();
                $('#took_medicine_no').prop('checked', true);
                $('#medicine').val('').removeClass('is-invalid');
                $('#medicine_quantity').val('').removeClass('is-invalid');
                $('#quantityWarning').hide();
                const modal = new bootstrap.Modal(document.getElementById('logVisitModal'));
                modal.show();
            });

            // Form validation
            $('#logVisitForm').on('submit', function(e) {
                const formData = $(this).serializeArray();
                console.log('[SSCMS Log Visit] Form submitting:', formData);

                const tookMedicine = $('input[name="took_medicine"]:checked').val();
                const reason = $('#reason').val();
                const medicine = $('#medicine').val();
                const quantity = parseInt($('#medicine_quantity').val()) || 0;

                console.log('[SSCMS Log Visit] Validation data:', {
                    tookMedicine,
                    reason,
                    medicine,
                    quantity
                });

                if (!reason) {
                    e.preventDefault();
                    alert('Please select a reason for the visit.');
                    $('#reason').addClass('is-invalid').focus();
                    return;
                } else {
                    $('#reason').removeClass('is-invalid');
                }

                if (reason === 'Other' && !$('#other_reason').val().trim()) {
                    e.preventDefault();
                    alert('Please specify the other reason.');
                    $('#other_reason').addClass('is-invalid').focus();
                    return;
                } else {
                    $('#other_reason').removeClass('is-invalid');
                }

                if (tookMedicine === 'Yes') {
                    if (!medicine) {
                        e.preventDefault();
                        alert('Please select a medicine.');
                        $('#medicine').addClass('is-invalid').focus();
                        return;
                    } else {
                        $('#medicine').removeClass('is-invalid');
                    }

                    if (!quantity || quantity <= 0) {
                        e.preventDefault();
                        alert('Please enter a valid quantity greater than 0.');
                        $('#medicine_quantity').addClass('is-invalid').focus();
                        return;
                    } else {
                        $('#medicine_quantity').removeClass('is-invalid');
                    }

                    const availableQuantity = parseInt($('#medicine option:selected').data('quantity')) || 0;
                    if (quantity > availableQuantity) {
                        e.preventDefault();
                        alert('Quantity exceeds available stock in inventory!');
                        $('#medicine_quantity').addClass('is-invalid').focus();
                        $('#quantityWarning').show();
                        return;
                    } else {
                        $('#quantityWarning').hide();
                    }
                }

                $('#logSpinner').removeClass('d-none');
                $('#submitVisit').prop('disabled', true);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 5000 });
            $('.toast').toast('show');

            // Dynamically set required attributes
            $('input[name="took_medicine"]').on('change', function() {
                const tookMedicine = $(this).val();
                if (tookMedicine === 'Yes') {
                    $('#medicine').prop('required', true);
                    $('#medicine_quantity').prop('required', true);
                } else {
                    $('#medicine').prop('required', false);
                    $('#medicine_quantity').prop('required', false);
                }
            });
        });

        function checkOtherReason() {
            const reasonSelect = document.getElementById('reason');
            const otherReasonDiv = document.getElementById('otherReasonDiv');
            const otherReasonInput = document.getElementById('other_reason');

            if (reasonSelect.value === 'Other') {
                otherReasonDiv.style.display = 'block';
                otherReasonInput.required = true;
            } else {
                otherReasonDiv.style.display = 'none';
                otherReasonInput.required = false;
                otherReasonInput.value = '';
                otherReasonInput.classList.remove('is-invalid');
            }
        }

        function toggleMedicineOptions(show) {
            const medicineOptions = document.getElementById('medicineOptions');
            const medicineSelect = document.getElementById('medicine');
            const quantityInput = document.getElementById('medicine_quantity');
            const quantityWarning = document.getElementById('quantityWarning');

            if (show) {
                medicineOptions.style.display = 'block';
                medicineSelect.required = true;
                quantityInput.required = true;
                if (medicineSelect.value) {
                    updateMedicineQuantity();
                } else {
                    medicineSelect.value = '';
                    quantityInput.value = '';
                    quantityWarning.style.display = 'none';
                    medicineSelect.classList.remove('is-invalid');
                    quantityInput.classList.remove('is-invalid');
                }
            } else {
                medicineOptions.style.display = 'none';
                medicineSelect.required = false;
                quantityInput.required = false;
                medicineSelect.value = '';
                quantityInput.value = '';
                quantityWarning.style.display = 'none';
                medicineSelect.classList.remove('is-invalid');
                quantityInput.classList.remove('is-invalid');
            }
        }

        function updateMedicineQuantity() {
            const medicineSelect = document.getElementById('medicine');
            const quantityInput = document.getElementById('medicine_quantity');
            const quantityWarning = document.getElementById('quantityWarning');
            const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
            const availableQuantity = parseInt(selectedOption.getAttribute('data-quantity')) || 0;

            console.log('[SSCMS Log Visit] Updating medicine quantity:', {
                medicine: medicineSelect.value,
                availableQuantity
            });

            if (quantityInput && medicineSelect.value) {
                quantityInput.max = availableQuantity;
                quantityInput.value = availableQuantity > 0 ? 1 : 0;
                quantityInput.classList.remove('is-invalid');
                validateQuantity();
            }
        }

        function validateQuantity() {
            const medicineSelect = document.getElementById('medicine');
            const quantityInput = document.getElementById('medicine_quantity');
            const quantityWarning = document.getElementById('quantityWarning');
            const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
            const availableQuantity = parseInt(selectedOption.getAttribute('data-quantity')) || 0;
            const enteredQuantity = parseInt(quantityInput.value) || 0;

            console.log('[SSCMS Log Visit] Validating quantity:', {
                enteredQuantity,
                availableQuantity
            });

            if (enteredQuantity > availableQuantity || enteredQuantity <= 0) {
                quantityWarning.style.display = 'block';
                quantityInput.value = availableQuantity > 0 ? availableQuantity : 0;
                quantityInput.classList.add('is-invalid');
            } else {
                quantityWarning.style.display = 'none';
                quantityInput.classList.remove('is-invalid');
            }
        }
    </script>
</body>
</html>