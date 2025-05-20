<?php
session_start();
require_once 'config/database.php';

// Function to validate session
function isValidSession($conn, $user_id, $session_id) {
    try {
        $stmt = $conn->prepare("SELECT last_active FROM sessions WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$user_id, $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        return $session && (time() - strtotime($session['last_active']) < 3600); // 1-hour session timeout
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

// Prevent logged-in users from accessing login page
if (isset($_SESSION['user_id']) && isValidSession($conn, $_SESSION['user_id'], session_id())) {
    header('Location: /SSCMS/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
        $remember = isset($_POST['remember']) ? true : false;

        if (!$email || !$password) {
            throw new Exception('Email and password are required.');
        }

        $stmt = $conn->prepare("SELECT id, name, email, password, admin_category, profile_picture FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid email or password.');
        }

        // Clear any existing sessions for the user
        $stmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // Start new session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['admin_category'] = $user['admin_category'];
        $_SESSION['profile_picture'] = $user['profile_picture'];

        if ($remember) {
            session_set_cookie_params(30 * 24 * 60 * 60); // 30 days
            session_regenerate_id(true);
        } else {
            session_set_cookie_params(0); // Session cookie
        }

        // Insert new session
        $stmt = $conn->prepare("INSERT INTO sessions (user_id, session_id, last_active) VALUES (?, ?, NOW())");
        $stmt->execute([$user['id'], session_id()]);

        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Login successful!', 'redirect' => '/SSCMS/dashboard.php']);
        exit;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Login">
    <meta name="author" content="ICCB">
    <title>Login - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --background: #f3f4f6;
            --card-bg: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border: #d1d5db;
            --danger: #dc2626;
            --success: #059669;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 1rem;
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            background: var(--card-bg);
        }
        .card-header {
            background: var(--card-bg);
            border-bottom: 0.5px solid var(--border);
            padding: 1rem;
            text-align: center;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .form-control {
            font-size: 0.85rem;
            border-radius: 0.375rem;
            border: 0.5px solid var(--border);
            background: var(--card-bg);
            color: var(--text-primary);
            height: 38px;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(2, 132, 199, 0.25);
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            font-size: 0.85rem;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .toast {
            border-radius: 0.375rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <img src="/SSCMS/assets/img/ICCLOGO.png" alt="ICC Logo" style="max-width: 80px; margin-bottom: 0.5rem;">
                <h4>SSCMS Login</h4>
            </div>
            <div class="card-body">
                <form id="loginForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i>Login</button>
                </form>
            </div>
        </div>
    </div>
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="loginToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Login] Initialized');
            const form = document.getElementById('loginForm');
            const toastEl = document.getElementById('loginToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl);
            if (form) {
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
                    const formData = new FormData(form);
                    $.ajax({
                        url: '/SSCMS/login.php',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Login] AJAX Success:', response);
                            if (response.success) {
                                toastBody.textContent = response.message || 'Login successful!';
                                toastBody.style.color = 'var(--success)';
                                toast.show();
                                setTimeout(() => {
                                    window.location.href = response.redirect || '/SSCMS/dashboard.php';
                                }, 1000);
                            } else {
                                toastBody.textContent = response.message || 'Login failed.';
                                toastBody.style.color = 'var(--danger)';
                                toast.show();
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('[SSCMS Login] AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                            toastBody.textContent = 'Error connecting to server: ' + textStatus;
                            toastBody.style.color = 'var(--danger)';
                            toast.show();
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>