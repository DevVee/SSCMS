<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

try {
    if (!isset($_POST['action'])) {
        sendResponse(false, 'No action specified');
    }

    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    $validCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'];
    $academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College'];

    switch ($action) {
        case 'add':
            $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
            $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
            $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING);
            $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
            $grade_year = filter_input(INPUT_POST, 'grade_year', FILTER_SANITIZE_STRING);
            $program_section = filter_input(INPUT_POST, 'program_section', FILTER_SANITIZE_STRING);
            $guardian_contact = filter_input(INPUT_POST, 'guardian_contact', FILTER_SANITIZE_STRING);

            if (!$last_name || !$first_name || !$gender || !$category) {
                sendResponse(false, 'Required fields are missing');
            }

            if (!in_array($category, $validCategories)) {
                sendResponse(false, 'Invalid category');
            }

            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                sendResponse(false, 'Invalid gender');
            }

            if (in_array($category, $academicCategories) && $category !== 'Alumni') {
                if ($grade_year) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM grade_years WHERE name = ? AND category = ?");
                    $stmt->execute([$grade_year, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid grade/year for the selected category');
                    }
                }
                if ($program_section) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM program_sections WHERE name = ? AND category = ?");
                    $stmt->execute([$program_section, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid program/section for the selected category');
                    }
                }
            } else {
                $grade_year = null;
                $program_section = null;
                $guardian_contact = null;
            }

            $stmt = $conn->prepare("
                INSERT INTO patients (last_name, first_name, middle_name, gender, category, grade_year, program_section, guardian_contact)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $last_name,
                $first_name,
                $middle_name ?: null,
                $gender,
                $category,
                $grade_year,
                $program_section,
                $guardian_contact
            ]);

            $_SESSION['success_message'] = 'Patient added successfully';
            sendResponse(true, 'Patient added successfully');

        case 'update':
            $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
            $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
            $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
            $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING);
            $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
            $grade_year = filter_input(INPUT_POST, 'grade_year', FILTER_SANITIZE_STRING);
            $program_section = filter_input(INPUT_POST, 'program_section', FILTER_SANITIZE_STRING);
            $guardian_contact = filter_input(INPUT_POST, 'guardian_contact', FILTER_SANITIZE_STRING);

            if (!$patient_id || !$last_name || !$first_name || !$gender || !$category) {
                sendResponse(false, 'Required fields are missing');
            }

            if (!in_array($category, $validCategories)) {
                sendResponse(false, 'Invalid category');
            }

            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                sendResponse(false, 'Invalid gender');
            }

            if (in_array($category, $academicCategories) && $category !== 'Alumni') {
                if ($grade_year) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM grade_years WHERE name = ? AND category = ?");
                    $stmt->execute([$grade_year, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid grade/year for the selected category');
                    }
                }
                if ($program_section) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM program_sections WHERE name = ? AND category = ?");
                    $stmt->execute([$program_section, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid program/section for the selected category');
                    }
                }
            } else {
                $grade_year = null;
                $program_section = null;
                $guardian_contact = null;
            }

            $stmt = $conn->prepare("
                UPDATE patients
                SET last_name = ?, first_name = ?, middle_name = ?, gender = ?, category = ?, grade_year = ?, program_section = ?, guardian_contact = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $last_name,
                $first_name,
                $middle_name ?: null,
                $gender,
                $category,
                $grade_year,
                $program_section,
                $guardian_contact,
                $patient_id
            ]);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'No changes made or patient not found');
            }

            $_SESSION['success_message'] = 'Patient updated successfully';
            sendResponse(true, 'Patient updated successfully');

        case 'move':
            $patient_ids = filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_STRING);
            $new_category = filter_input(INPUT_POST, 'new_category', FILTER_SANITIZE_STRING);
            $new_grade_year = filter_input(INPUT_POST, 'new_grade_year', FILTER_SANITIZE_STRING);
            $new_program_section = filter_input(INPUT_POST, 'new_program_section', FILTER_SANITIZE_STRING);

            if (!$patient_ids || !$new_category) {
                sendResponse(false, 'Patient IDs and new category are required');
            }

            if (!in_array($new_category, $validCategories)) {
                sendResponse(false, 'Invalid new category');
            }

            $patient_ids_array = array_filter(array_map('intval', explode(',', $patient_ids)));
            if (empty($patient_ids_array)) {
                sendResponse(false, 'No valid patient IDs provided');
            }

            if (in_array($new_category, $academicCategories) && $new_category !== 'Alumni') {
                if ($new_grade_year) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM grade_years WHERE name = ? AND category = ?");
                    $stmt->execute([$new_grade_year, $new_category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid new grade/year for the selected category');
                    }
                }
                if ($new_program_section) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM program_sections WHERE name = ? AND category = ?");
                    $stmt->execute([$new_program_section, $new_category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid new program/section for the selected category');
                    }
                }
            } else {
                $new_grade_year = null;
                $new_program_section = null;
            }

            $placeholders = implode(',', array_fill(0, count($patient_ids_array), '?'));
            $stmt = $conn->prepare("
                UPDATE patients
                SET category = ?, grade_year = ?, program_section = ?
                WHERE id IN ($placeholders)
            ");
            $params = array_merge([$new_category, $new_grade_year, $new_program_section], $patient_ids_array);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'No patients were moved');
            }

            $_SESSION['success_message'] = 'Patients moved successfully';
            sendResponse(true, 'Patients moved successfully');

        case 'delete':
            $patient_ids = filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_STRING);

            if (!$patient_ids) {
                sendResponse(false, 'No patient IDs provided');
            }

            $patient_ids_array = array_filter(array_map('intval', explode(',', $patient_ids)));
            if (empty($patient_ids_array)) {
                sendResponse(false, 'No valid patient IDs provided');
            }

            $placeholders = implode(',', array_fill(0, count($patient_ids_array), '?'));
            $stmt = $conn->prepare("DELETE FROM patients WHERE id IN ($placeholders)");
            $stmt->execute($patient_ids_array);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'No patients were deleted');
            }

            $_SESSION['success_message'] = 'Patients deleted successfully';
            sendResponse(true, 'Patients deleted successfully');

        default:
            sendResponse(false, 'Invalid action');
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}