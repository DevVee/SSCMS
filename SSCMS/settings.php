<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Settings] Unauthorized access: " . (isset($_SESSION['user_id']) ? "user_id: {$_SESSION['user_id']}" : "no session"));
    header('Location: /SSCMS/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = [];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX responses
function send_json_response($success, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Fetch current user data
try {
    $stmt = $conn->prepare("SELECT name, email, admin_category, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current_user) {
        error_log("[SSCMS Settings] User not found: user_id=$user_id");
        send_json_response(false, 'User not found.');
    }
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch user error: " . $e->getMessage());
    send_json_response(false, 'Failed to fetch user data.');
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $admin_category = filter_input(INPUT_POST, 'admin_category', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Name and valid email are required.');
        }
        if (!$admin_category || !in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }

        // Handle profile picture upload
        $profile_picture = $current_user['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);
            $file_size = $_FILES['profile_picture']['size'];

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Only JPEG, PNG, or GIF images are allowed.');
            }
            if ($file_size > $max_size) {
                throw new Exception('Image size must be less than 2MB.');
            }

            $upload_dir = 'Uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $target = $upload_dir . $filename;

            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
                throw new Exception('Failed to upload profile picture.');
            }
            $profile_picture = '/SSCMS/' . $target;

            if ($current_user['profile_picture'] && file_exists(substr($current_user['profile_picture'], 1))) {
                unlink(substr($current_user['profile_picture'], 1));
            }
        }

        // Update user
        $query = "UPDATE users SET name = ?, email = ?, admin_category = ?, profile_picture = ?, updated_at = NOW()";
        $params = [$name, $email, $admin_category, $profile_picture];
        if ($password) {
            $query .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $query .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        // Refresh session
        $_SESSION['user_name'] = $name;
        $_SESSION['admin_category'] = $admin_category;
        $_SESSION['profile_picture'] = $profile_picture;

        send_json_response(true, 'Profile updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Profile update error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Create Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'new_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'new_email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_STRING);
        $admin_category = filter_input(INPUT_POST, 'new_admin_category', FILTER_SANITIZE_STRING);

        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$password || !$admin_category) {
            throw new Exception('All fields are required for new admin.');
        }
        if (!in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }

        // Check email uniqueness
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists.');
        }

        // Insert new admin
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, admin_category, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $admin_category]);
        send_json_response(true, 'Admin account created successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Create admin error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Edit Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $edit_id = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'edit_email', FILTER_SANITIZE_EMAIL);
        $admin_category = filter_input(INPUT_POST, 'edit_admin_category', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'edit_password', FILTER_SANITIZE_STRING);

        if (!$edit_id || !$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$admin_category) {
            throw new Exception('All fields are required for editing admin.');
        }
        if (!in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }

        // Check email uniqueness (exclude current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $edit_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists.');
        }

        // Update admin
        $query = "UPDATE users SET name = ?, email = ?, admin_category = ?, updated_at = NOW()";
        $params = [$name, $email, $admin_category];
        if ($password) {
            $query .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $query .= " WHERE id = ?";
        $params[] = $edit_id;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        send_json_response(true, 'Admin account updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Edit admin error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Delete Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
        if (!$delete_id || $delete_id == $user_id) {
            throw new Exception('Cannot delete own account or invalid ID.');
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        send_json_response(true, 'Admin account deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Delete admin error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Reset Admin Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_admin_password') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $reset_id = filter_input(INPUT_POST, 'reset_id', FILTER_VALIDATE_INT);
        if (!$reset_id || $reset_id == $user_id) {
            throw new Exception('Cannot reset own password or invalid ID.');
        }

        // Generate random temporary password
        $temp_password = bin2hex(random_bytes(8)); // 16-char random string
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $reset_id]);

        // Log action
        error_log("[SSCMS Settings] Password reset for user_id=$reset_id by admin_id=$user_id");

        send_json_response(true, 'Password reset successfully!', ['temp_password' => $temp_password]);
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Reset password error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Add Program Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_section') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'section_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'section_category', FILTER_SANITIZE_STRING);

        if (!$name || strlen($name) > 100) {
            throw new Exception('Section name is required and must be 100 characters or less.');
        }
        if (!$category || !in_array($category, ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'])) {
            throw new Exception('Invalid section category.');
        }

        $stmt = $conn->prepare("INSERT INTO program_sections (name, category) VALUES (?, ?)");
        $stmt->execute([$name, $category]);
        send_json_response(true, 'Program section added successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Add section error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Edit Program Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_section') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $edit_id = filter_input(INPUT_POST, 'edit_section_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'edit_section_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'edit_section_category', FILTER_SANITIZE_STRING);

        if (!$edit_id || !$name || strlen($name) > 100) {
            throw new Exception('Section name is required and must be 100 characters or less.');
        }
        if (!$category || !in_array($category, ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'])) {
            throw new Exception('Invalid section category.');
        }

        $stmt = $conn->prepare("UPDATE program_sections SET name = ?, category = ? WHERE id = ?");
        $stmt->execute([$name, $category, $edit_id]);
        send_json_response(true, 'Program section updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Edit section error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Delete Program Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_section') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $delete_id = filter_input(INPUT_POST, 'delete_section_id', FILTER_VALIDATE_INT);
        if (!$delete_id) {
            throw new Exception('Invalid section ID.');
        }

        $stmt = $conn->prepare("DELETE FROM program_sections WHERE id = ?");
        $stmt->execute([$delete_id]);
        send_json_response(true, 'Program section deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Delete section error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Add Grade Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_year') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'year_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'year_category', FILTER_SANITIZE_STRING);

        if (!$name || strlen($name) > 50) {
            throw new Exception('Grade year name is required and must be 50 characters or less.');
        }
        if (!$category || !in_array($category, ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'])) {
            throw new Exception('Invalid grade year category.');
        }

        $stmt = $conn->prepare("INSERT INTO grade_years (name, category) VALUES (?, ?)");
        $stmt->execute([$name, $category]);
        send_json_response(true, 'Grade year added successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Add year error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Edit Grade Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_year') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $edit_id = filter_input(INPUT_POST, 'edit_year_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'edit_year_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'edit_year_category', FILTER_SANITIZE_STRING);

        if (!$edit_id || !$name || strlen($name) > 50) {
            throw new Exception('Grade year name is required and must be 50 characters or less.');
        }
        if (!$category || !in_array($category, ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'])) {
            throw new Exception('Invalid grade year category.');
        }

        $stmt = $conn->prepare("UPDATE grade_years SET name = ?, category = ? WHERE id = ?");
        $stmt->execute([$name, $category, $edit_id]);
        send_json_response(true, 'Grade year updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Edit year error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Delete Grade Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_year') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $delete_id = filter_input(INPUT_POST, 'delete_year_id', FILTER_VALIDATE_INT);
        if (!$delete_id) {
            throw new Exception('Invalid grade year ID.');
        }

        $stmt = $conn->prepare("DELETE FROM grade_years WHERE id = ?");
        $stmt->execute([$delete_id]);
        send_json_response(true, 'Grade year deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Delete year error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Fetch all admins
try {
    $stmt = $conn->query("SELECT id, name, email, admin_category, profile_picture FROM users");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch admins error: " . $e->getMessage());
    $admins = [];
}

// Fetch active users
try {
    $stmt = $conn->prepare("SELECT u.id, u.name, u.admin_category, s.last_active 
                            FROM sessions s 
                            JOIN users u ON s.user_id = u.id 
                            WHERE s.last_active >= NOW() - INTERVAL 30 MINUTE");
    $stmt->execute();
    $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch active users error: " . $e->getMessage());
    $active_users = [];
}

// Fetch program sections and grade years
try {
    $stmt = $conn->query("SELECT id, name, category FROM program_sections");
    $program_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch sections error: " . $e->getMessage());
    $program_sections = [];
}

try {
    $stmt = $conn->query("SELECT id, name, category FROM grade_years");
    $grade_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch years error: " . $e->getMessage());
    $grade_years = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Settings">
    <meta name="author" content="ICCB">
    <title>Settings - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --background: #f9fafb;
            --card-bg: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border: #d1d5db;
            --danger: #dc2626;
            --success: #059669;
            --transition-speed: 0.3s;
            --sidebar-width: 180px;
            --sidebar-collapsed-width: 50px;
            --header-height: 48px;
        }

        [data-theme="dark"] {
            --primary: #4dabf7;
            --primary-dark: #2b6cb0;
            --background: #1a202c;
            --card-bg: #2d3748;
            --text-primary: #e2e8f0;
            --text-secondary: #a0aec0;
            --border: #4a5568;
            --danger: #f56565;
            --success: #4fd1c5;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            margin: 0;
            padding: 0;
            font-size: 0.8rem;
        }

        .banner {
            background: linear-gradient(180deg, #e0f2fe 0%, #f9fafb 100%);
            padding: 0.75rem 0;
            text-align: center;
            border-bottom: 1px solid var(--border);
            margin-bottom: 0.75rem;
        }

        .banner img {
            max-width: 100%;
            width: 600px;
            height: auto;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: calc(var(--header-height) + 0.5rem) 0.75rem 0.75rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1200px;
            padding: 0 0.75rem;
        }

        .dashboard-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .dashboard-title i {
            color: var(--primary);
            font-size: 0.9rem;
            background-color: #e0f2fe;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-breadcrumb {
            padding: 0.4rem 0.5rem;
            background-color: var(--card-bg);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .custom-breadcrumb .breadcrumb-item {
            color: var(--text-secondary);
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 500;
        }

        .card {
            border: none;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            background: var(--card-bg);
            margin-bottom: 0.75rem;
        }

        .card-header {
            background: var(--primary);
            color: #ffffff;
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            font-size: 0.8rem;
            border-radius: 6px 6px 0 0;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .form-control, .form-select {
            font-size: 0.75rem;
            border-radius: 4px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text-primary);
            height: 32px;
            padding: 0.3rem 0.5rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.15rem rgba(2, 132, 199, 0.25);
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
        }

        .btn {
            font-size: 0.75rem;
            border-radius: 4px;
            padding: 0.3rem 0.6rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            transition: all var(--transition-speed);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background-color: #b91c1c;
            border-color: #b91c1c;
        }

        .btn-warning {
            background-color: #f59e0b;
            border-color: #f59e0b;
            color: #ffffff;
        }

        .btn-warning:hover {
            background-color: #d97706;
            border-color: #d97706;
        }

        .profile-picture-preview {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border);
            margin-bottom: 0.5rem;
        }

        .table {
            font-size: 0.75rem;
            margin-bottom: 0;
        }

        .table th, .table td {
            vertical-align: middle;
            padding: 0.3rem;
            border-color: var(--border);
        }

        .table th {
            font-weight: 500;
            background: #f1f5f9;
            color: var(--text-primary);
        }

        .nav-tabs {
            border-bottom: 1px solid var(--border);
            margin-bottom: 0.75rem;
        }

        .nav-tabs .nav-link {
            font-size: 0.8rem;
            color: var(--text-secondary);
            border-radius: 4px 4px 0 0;
            padding: 0.4rem 0.75rem;
        }

        .nav-tabs .nav-link.active {
            background-color: var(--card-bg);
            color: var(--primary);
            border-bottom: none;
        }

        .toast {
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            font-size: 0.75rem;
        }

        .modal-content {
            border-radius: 6px;
            font-size: 0.75rem;
        }

        .modal-header {
            padding: 0.5rem 0.75rem;
            background: var(--primary);
            color: #ffffff;
        }

        .modal-title {
            font-size: 0.85rem;
        }

        .modal-body {
            padding: 0.75rem;
        }

        footer {
            background: var(--card-bg);
            color: var(--text-secondary);
            font-size: 0.75rem;
            padding: 0.75rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
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
                padding: 0.5rem;
            }
            .banner img {
                width: 400px;
            }
            .table {
                font-size: 0.7rem;
            }
            .table th, .table td {
                padding: 0.2rem;
            }
            .form-control, .form-select {
                font-size: 0.7rem;
                height: 30px;
            }
            .btn {
                font-size: 0.7rem;
                padding: 0.2rem 0.5rem;
            }
            .profile-picture-preview {
                width: 50px;
                height: 50px;
            }
            .dashboard-title {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Banner -->
    <div class="banner fade-in">
        <img src="assets/img/ICC_Banner.png" alt="Immaculate Conception College of Balayan, Inc. Banner">
    </div>

    <?php include 'includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <h1 class="dashboard-title fade-in">
                    <i class="fas fa-cog"></i>
                    System Settings
                </h1>
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/SSCMS/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>

                <!-- Toast Container -->
                <div class="toast-container position-fixed bottom-0 end-0 p-2">
                    <div id="settingsToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header">
                            <strong class="me-auto">Notification</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body"></div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3 fade-in" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">My Profile</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="active-users-tab" data-bs-toggle="tab" data-bs-target="#active-users" type="button" role="tab" aria-controls="active-users" aria-selected="false">Active Users</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manage-admins-tab" data-bs-toggle="tab" data-bs-target="#manage-admins" type="button" role="tab" aria-controls="manage-admins" aria-selected="false">Manage Admins</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab" aria-controls="sections" aria-selected="false">Program Sections</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="years-tab" data-bs-toggle="tab" data-bs-target="#years" type="button" role="tab" aria-controls="years" aria-selected="false">Grade Years</button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabContent">
                    <!-- My Profile -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-user-circle"></i> Edit Profile
                            </div>
                            <div class="card-body">
                                <form id="profileForm" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label for="name" class="form-label">Name</label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($current_user['name']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($current_user['email']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label for="admin_category" class="form-label">Role</label>
                                        <select class="form-select" id="admin_category" name="admin_category" required>
                                            <option value="Nurse" <?= $current_user['admin_category'] === 'Nurse' ? 'selected' : '' ?>>Nurse</option>
                                            <option value="Clinic Staff" <?= $current_user['admin_category'] === 'Clinic Staff' ? 'selected' : '' ?>>Clinic Staff</option>
                                            <option value="Doctor" <?= $current_user['admin_category'] === 'Doctor' ? 'selected' : '' ?>>Doctor</option>
                                            <option value="Dentist" <?= $current_user['admin_category'] === 'Dentist' ? 'selected' : '' ?>>Dentist</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label for="password" class="form-label">New Password (optional)</label>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current">
                                    </div>
                                    <div class="mb-2">
                                        <label for="profile_picture" class="form-label">Profile Picture</label>
                                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                                        <?php if ($current_user['profile_picture']): ?>
                                            <img src="<?= htmlspecialchars($current_user['profile_picture']) ?>" alt="Profile Picture" class="profile-picture-preview">
                                        <?php else: ?>
                                            <div class="profile-picture-preview bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-user-md" style="color: var(--primary); font-size: 1.5rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Active Users -->
                    <div class="tab-pane fade" id="active-users" role="tabpanel" aria-labelledby="active-users-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-users"></i> Active Users
                            </div>
                            <div class="card-body">
                                <?php if (empty($active_users)): ?>
                                    <p class="text-muted">No active users at this time.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Role</th>
                                                    <th>Last Active</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($active_users as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                                        <td><?= htmlspecialchars($user['admin_category']) ?></td>
                                                        <td><?= date('Y-m-d H:i:s', strtotime($user['last_active'])) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Manage Admins -->
                    <div class="tab-pane fade" id="manage-admins" role="tabpanel" aria-labelledby="manage-admins-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-user-plus"></i> Create New Admin
                            </div>
                            <div class="card-body">
                                <form id="createAdminForm">
                                    <input type="hidden" name="action" value="create_admin">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label for="new_name" class="form-label">Name</label>
                                            <input type="text" class="form-control" id="new_name" name="new_name" required>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label for="new_email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="new_email" name="new_email" required>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label for="new_password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="new_admin_category" class="form-label">Role</label>
                                        <select class="form-select" id="new_admin_category" name="new_admin_category" required>
                                            <option value="Nurse">Nurse</option>
                                            <option value="Clinic Staff">Clinic Staff</option>
                                            <option value="Doctor">Doctor</option>
                                            <option value="Dentist">Dentist</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create</button>
                                </form>
                            </div>
                        </div>

                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-user-edit"></i> Manage Existing Admins
                            </div>
                            <div class="card-body">
                                <?php if (empty($admins)): ?>
                                    <p class="text-muted">No admin accounts found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Password</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($admins as $admin): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($admin['name']) ?></td>
                                                        <td><?= htmlspecialchars($admin['email']) ?></td>
                                                        <td><?= htmlspecialchars($admin['admin_category']) ?></td>
                                                        <td>
                                                            <?php if ($admin['id'] != $user_id): ?>
                                                                <button class="btn btn-warning btn-sm reset-password-btn" data-id="<?= $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['name']) ?>" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"><i class="fas fa-key"></i> Reset</button>
                                                            <?php else: ?>
                                                                <span class="text-muted">Own account</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-primary btn-sm edit-admin-btn" data-id="<?= $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['name']) ?>" data-email="<?= htmlspecialchars($admin['email']) ?>" data-admin_category="<?= htmlspecialchars($admin['admin_category']) ?>" data-bs-toggle="modal" data-bs-target="#editAdminModal"><i class="fas fa-edit"></i></button>
                                                            <?php if ($admin['id'] != $user_id): ?>
                                                                <form class="d-inline delete-admin-form" style="display:inline;">
                                                                    <input type="hidden" name="action" value="delete_admin">
                                                                    <input type="hidden" name="delete_id" value="<?= $admin['id'] ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Program Sections -->
                    <div class="tab-pane fade" id="sections" role="tabpanel" aria-labelledby="sections-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-book"></i> Add Program Section
                            </div>
                            <div class="card-body">
                                <form id="addSectionForm">
                                    <input type="hidden" name="action" value="add_section">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-2">
                                        <label for="section_name" class="form-label">Section Name</label>
                                        <input type="text" class="form-control" id="section_name" name="section_name" maxlength="100" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="section_category" class="form-label">Category</label>
                                        <select class="form-select" id="section_category" name="section_category" required>
                                            <option value="Pre School">Pre School</option>
                                            <option value="Elementary">Elementary</option>
                                            <option value="JHS">JHS</option>
                                            <option value="SHS">SHS</option>
                                            <option value="College">College</option>
                                            <option value="Alumni">Alumni</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-book"></i> Existing Sections
                            </div>
                            <div class="card-body">
                                <?php if (empty($program_sections)): ?>
                                    <p class="text-muted">No program sections found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Category</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($program_sections as $section): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($section['name']) ?></td>
                                                        <td><?= htmlspecialchars($section['category']) ?></td>
                                                        <td>
                                                            <button class="btn btn-primary btn-sm edit-section-btn" data-id="<?= $section['id'] ?>" data-name="<?= htmlspecialchars($section['name']) ?>" data-category="<?= htmlspecialchars($section['category']) ?>" data-bs-toggle="modal" data-bs-target="#editSectionModal"><i class="fas fa-edit"></i></button>
                                                            <form class="d-inline delete-section-form" style="display:inline;">
                                                                <input type="hidden" name="action" value="delete_section">
                                                                <input type="hidden" name="delete_section_id" value="<?= $section['id'] ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Grade Years -->
                    <div class="tab-pane fade" id="years" role="tabpanel" aria-labelledby="years-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-graduation-cap"></i> Add Grade Year
                            </div>
                            <div class="card-body">
                                <form id="addYearForm">
                                    <input type="hidden" name="action" value="add_year">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-2">
                                        <label for="year_name" class="form-label">Grade Year Name</label>
                                        <input type="text" class="form-control" id="year_name" name="year_name" maxlength="50" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="year_category" class="form-label">Category</label>
                                        <select class="form-select" id="year_category" name="year_category" required>
                                            <option value="Pre School">Pre School</option>
                                            <option value="Elementary">Elementary</option>
                                            <option value="JHS">JHS</option>
                                            <option value="SHS">SHS</option>
                                            <option value="College">College</option>
                                            <option value="Alumni">Alumni</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-graduation-cap"></i> Existing Grade Years
                            </div>
                            <div class="card-body">
                                <?php if (empty($grade_years)): ?>
                                    <p class="text-muted">No grade years found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Category</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($grade_years as $year): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($year['name']) ?></td>
                                                        <td><?= htmlspecialchars($year['category']) ?></td>
                                                        <td>
                                                            <button class="btn btn-primary btn-sm edit-year-btn" data-id="<?= $year['id'] ?>" data-name="<?= htmlspecialchars($year['name']) ?>" data-category="<?= htmlspecialchars($year['category']) ?>" data-bs-toggle="modal" data-bs-target="#editYearModal"><i class="fas fa-edit"></i></button>
                                                            <form class="d-inline delete-year-form" style="display:inline;">
                                                                <input type="hidden" name="action" value="delete_year">
                                                                <input type="hidden" name="delete_year_id" value="<?= $year['id'] ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <footer class="fade-in">
            <div class="container-fluid">
                <div class="text-muted">
                    <i class="fas fa-hospital me-1"></i>
                    IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
                </div>
            </div>
        </footer>
    </div>

    <!-- Edit Admin Modal -->
    <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAdminModalLabel">Edit Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAdminForm">
                        <input type="hidden" name="action" value="edit_admin">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="edit_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="edit_name" name="edit_name" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="edit_email" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_admin_category" class="form-label">Role</label>
                            <select class="form-select" id="edit_admin_category" name="edit_admin_category" required>
                                <option value="Nurse">Nurse</option>
                                <option value="Clinic Staff">Clinic Staff</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Dentist">Dentist</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="edit_password" class="form-label">New Password (optional)</label>
                            <input type="password" class="form-control" id="edit_password" name="edit_password" placeholder="Leave blank to keep current">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for <strong id="reset-admin-name"></strong>?</p>
                    <p class="text-muted">A temporary password will be generated. The admin must change it after logging in.</p>
                    <form id="resetPasswordForm">
                        <input type="hidden" name="action" value="reset_admin_password">
                        <input type="hidden" name="reset_id" id="reset_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Reset Password</button>
                    </form>
                    <div id="temp-password-container" class="mt-2 d-none">
                        <p><strong>Temporary Password:</strong> <code id="temp-password"></code></p>
                        <p class="text-muted">Copy this password and share it securely with the admin.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSectionModalLabel">Edit Program Section</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editSectionForm">
                        <input type="hidden" name="action" value="edit_section">
                        <input type="hidden" name="edit_section_id" id="edit_section_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="edit_section_name" class="form-label">Section Name</label>
                            <input type="text" class="form-control" id="edit_section_name" name="edit_section_name" maxlength="100" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_section_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_section_category" name="edit_section_category" required>
                                <option value="Pre School">Pre School</option>
                                <option value="Elementary">Elementary</option>
                                <option value="JHS">JHS</option>
                                <option value="SHS">SHS</option>
                                <option value="College">College</option>
                                <option value="Alumni">Alumni</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Year Modal -->
    <div class="modal fade" id="editYearModal" tabindex="-1" aria-labelledby="editYearModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editYearModalLabel">Edit Grade Year</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editYearForm">
                        <input type="hidden" name="action" value="edit_year">
                        <input type="hidden" name="edit_year_id" id="edit_year_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="edit_year_name" class="form-label">Grade Year Name</label>
                            <input type="text" class="form-control" id="edit_year_name" name="edit_year_name" maxlength="50" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_year_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_year_category" name="edit_year_category" required>
                                <option value="Pre School">Pre School</option>
                                <option value="Elementary">Elementary</option>
                                <option value="JHS">JHS</option>
                                <option value="SHS">SHS</option>
                                <option value="College">College</option>
                                <option value="Alumni">Alumni</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Settings] Initialized');

            const toastEl = document.getElementById('settingsToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            // Generic form handler
            function handleFormSubmit(form, url, successMessage, isMultipart = false) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!form.checkValidity()) {
                        e.stopPropagation();
                        toastBody.textContent = 'Please fill in all required fields correctly.';
                        toastBody.style.color = 'var(--danger)';
                        toast.show();
                        form.classList.add('was-validated');
                        return;
                    }

                    const data = isMultipart ? new FormData(form) : $(form).serialize();
                    $.ajax({
                        url: url,
                        method: 'POST',
                        data: data,
                        processData: !isMultipart,
                        contentType: isMultipart ? false : 'application/x-www-form-urlencoded',
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Settings] AJAX Success:', response);
                            if (response.success) {
                                toastBody.textContent = response.message || successMessage;
                                toastBody.style.color = 'var(--success)';
                                toast.show();
                                if (form.id === 'profileForm') {
                                    const profileDropdown = document.querySelector('#profileDropdown span');
                                    const sidebarHeader = document.querySelector('.sidebar-header p');
                                    const sidebarRole = document.querySelector('.sidebar-header small');
                                    if (profileDropdown) profileDropdown.textContent = form.querySelector('[name="name"]').value;
                                    if (sidebarHeader) sidebarHeader.textContent = form.querySelector('[name="name"]').value;
                                    if (sidebarRole) sidebarRole.textContent = form.querySelector('[name="admin_category"]').value;
                                } else if (form.id === 'resetPasswordForm') {
                                    document.getElementById('temp-password').textContent = response.temp_password;
                                    document.getElementById('temp-password-container').classList.remove('d-none');
                                } else if (form.id !== 'editAdminForm' && form.id !== 'editSectionForm' && form.id !== 'editYearForm') {
                                    form.reset();
                                    form.classList.remove('was-validated');
                                }
                                if (form.id !== 'profileForm' && form.id !== 'resetPasswordForm') {
                                    setTimeout(() => location.reload(), 1000);
                                }
                            } else {
                                toastBody.textContent = response.message || 'Operation failed.';
                                toastBody.style.color = 'var(--danger)';
                                toast.show();
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('[SSCMS Settings] AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                            toastBody.textContent = 'Error: ' + textStatus;
                            toastBody.style.color = 'var(--danger)';
                            toast.show();
                        }
                    });
                });
            }

            // Profile Form
            handleFormSubmit(document.getElementById('profileForm'), '/SSCMS/settings.php', 'Profile updated successfully!', true);

            // Create Admin Form
            handleFormSubmit(document.getElementById('createAdminForm'), '/SSCMS/settings.php', 'Admin created successfully!');

            // Edit Admin Form
            const editButtons = document.querySelectorAll('.edit-admin-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_id').value = this.dataset.id;
                    document.getElementById('edit_name').value = this.dataset.name;
                    document.getElementById('edit_email').value = this.dataset.email;
                    document.getElementById('edit_admin_category').value = this.dataset.admin_category;
                });
            });
            handleFormSubmit(document.getElementById('editAdminForm'), '/SSCMS/settings.php', 'Admin updated successfully!');

            // Reset Password Form
            const resetButtons = document.querySelectorAll('.reset-password-btn');
            resetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('reset_id').value = this.dataset.id;
                    document.getElementById('reset-admin-name').textContent = this.dataset.name;
                    document.getElementById('temp-password-container').classList.add('d-none');
                });
            });
            handleFormSubmit(document.getElementById('resetPasswordForm'), '/SSCMS/settings.php', 'Password reset successfully!');

            // Delete Admin Forms
            const deleteForms = document.querySelectorAll('.delete-admin-form');
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this admin?')) return;
                    handleFormSubmit(form, '/SSCMS/settings.php', 'Admin deleted successfully!')();
                });
            });

            // Add Section Form
            handleFormSubmit(document.getElementById('addSectionForm'), '/SSCMS/settings.php', 'Section added successfully!');

            // Edit Section Form
            const editSectionButtons = document.querySelectorAll('.edit-section-btn');
            editSectionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_section_id').value = this.dataset.id;
                    document.getElementById('edit_section_name').value = this.dataset.name;
                    document.getElementById('edit_section_category').value = this.dataset.category;
                });
            });
            handleFormSubmit(document.getElementById('editSectionForm'), '/SSCMS/settings.php', 'Section updated successfully!');

            // Delete Section Forms
            const deleteSectionForms = document.querySelectorAll('.delete-section-form');
            deleteSectionForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this section?')) return;
                    handleFormSubmit(form, '/SSCMS/settings.php', 'Section deleted successfully!')();
                });
            });

            // Add Year Form
            handleFormSubmit(document.getElementById('addYearForm'), '/SSCMS/settings.php', 'Grade year added successfully!');

            // Edit Year Form
            const editYearButtons = document.querySelectorAll('.edit-year-btn');
            editYearButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_year_id').value = this.dataset.id;
                    document.getElementById('edit_year_name').value = this.dataset.name;
                    document.getElementById('edit_year_category').value = this.dataset.category;
                });
            });
            handleFormSubmit(document.getElementById('editYearForm'), '/SSCMS/settings.php', 'Grade year updated successfully!');

            // Delete Year Forms
            const deleteYearForms = document.querySelectorAll('.delete-year-form');
            deleteYearForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this grade year?')) return;
                    handleFormSubmit(form, '/SSCMS/settings.php', 'Grade year deleted successfully!')();
                });
            });
        });
    </script>
</body>
</html>