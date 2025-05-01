<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS New Appointment] Unauthorized access: no session");
    header('Location: /SSCMS/login.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System">
    <meta name="author" content="ICCB">
    <title>New Appointment - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --primary-dark: #4338ca;
            --success: #2ec4b6;
            --success-light: #e6f7f5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --accent: #8b5cf6;
            --accent-light: #ede9fe;
            --secondary: #6b7280;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --background: #f9fafb;
            --card-bg: #ffffff;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
            --transition-speed: 0.3s;
            --gradient-primary: linear-gradient(135deg, #4f46e5, #4338ca);
            --gradient-success: linear-gradient(135deg, #2ec4b6, #22d3ee);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #d97706);
            --gradient-danger: linear-gradient(135deg, #ef4444, #dc2626);
            --gradient-accent: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            font-size: 0.9rem;
            font-weight: 400;
            overflow-x: hidden;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: calc(var(--header-height) + 1rem) 1rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="%23f9fafb"/><path d="M0 0L100 100M100 0L0 100" stroke="%23e5e7eb" stroke-width="0.1"/></svg>');
            background-size: 20px 20px;
        }

        .container-fluid {
            max-width: 1280px;
            padding: 0 1rem;
        }

        .dashboard-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-left: 0.75rem;
            border-left: 4px solid var(--primary);
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            background: var(--card-bg);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 1rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .form-control, .form-select {
            font-size: 0.85rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text-primary);
            height: 36px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        textarea.form-control {
            height: auto;
            min-height: 100px;
        }

        .btn {
            font-size: 0.85rem;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca, #3730a3);
            transform: scale(1.05);
            box-shadow: 0 3px 6px rgba(79, 70, 229, 0.2);
        }

        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .breadcrumb {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }

        .toast {
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: none;
            min-width: 300px;
        }

        .toast.success { border-left: 3px solid var(--success); }
        .toast.error { border-left: 3px solid var(--danger); }

        .toast-header {
            background: var(--card-bg);
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            padding: 0.5rem 1rem;
        }

        .toast-body {
            padding: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        footer {
            background: var(--card-bg);
            border-top: 1px solid var(--border);
            padding: 1rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 1.5rem;
        }

        .flatpickr-calendar {
            font-size: 0.85rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .flatpickr-day.selected {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-up { animation: slideInUp 0.5s ease forwards; }
        .delay-100 { animation-delay: 0.1s; opacity: 0; }

        @media (max-width: 992px) {
            :root { --sidebar-width: var(--sidebar-collapsed-width); }
            .content { margin-left: var(--sidebar-width); }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .dashboard-title { font-size: 1.1rem; }
            .form-control, .form-select { font-size: 0.8rem; height: 34px; }
            .form-label { font-size: 0.8rem; }
            .btn { font-size: 0.8rem; padding: 0.4rem 0.8rem; }
            .breadcrumb { font-size: 0.75rem; }
            .card-header { font-size: 0.9rem; }
            .toast-body { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <h1 class="dashboard-title slide-up">
                    <i class="fas fa-calendar-plus me-2"></i>
                    New Appointment
                </h1>
                <nav aria-label="breadcrumb" class="slide-up delay-100">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/SSCMS/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/SSCMS/appointments/appointment-list.php">Appointments</a></li>
                        <li class="breadcrumb-item active" aria-current="page">New Appointment</li>
                    </ol>
                </nav>

                <div class="card slide-up delay-100">
                    <div class="card-header">
                        <div>
                            <i class="fas fa-calendar-plus me-2"></i>
                            Book a New Appointment
                        </div>
                        <a href="/SSCMS/appointments/appointment-list.php" class="btn btn-sm btn-outline-primary">View Appointments</a>
                    </div>
                    <div class="card-body">
                        <form id="appointmentForm" action="/SSCMS/appointments/submit_appointment.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="patient_name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="patient_name" id="patient_name" class="form-control" placeholder="Enter Full Name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category" id="category" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <option value="Pre School">Pre School</option>
                                        <option value="Elementary">Elementary</option>
                                        <option value="JHS">JHS</option>
                                        <option value="SHS">SHS</option>
                                        <option value="College">College</option>
                                        <option value="Alumni">Alumni</option>
                                        <option value="Non-Student">Non-Student</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="phone" id="phone" class="form-control" placeholder="Enter Phone Number" pattern="[0-9]{10,11}" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="appointee" class="form-label">Appoint With <span class="text-danger">*</span></label>
                                    <select name="appointee" id="appointee" class="form-select" required>
                                        <option value="">Select Appointee</option>
                                        <option value="Doctor">Doctor</option>
                                        <option value="Nurse">Nurse</option>
                                        <option value="Dentist">Dentist</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="appointment_date" class="form-label">Appointment Date <span class="text-danger">*</span></label>
                                    <input type="text" name="appointment_date" id="appointment_date" class="form-control flatpickr" placeholder="Select Date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="appointment_time" class="form-label">Appointment Time <span class="text-danger">*</span></label>
                                    <select name="appointment_time" id="appointment_time" class="form-select" required>
                                        <option value="">Select Time</option>
                                        <?php for ($hour = 7; $hour <= 16; $hour++): ?>
                                            <?php $time = sprintf("%02d:00:00", $hour); ?>
                                            <option value="<?= $time ?>"><?= date("h:i A", strtotime($time)) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                                    <textarea name="reason" id="reason" class="form-control" placeholder="Enter reason for visit" rows="4" required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Submit Appointment</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <footer>
            <div class="container-fluid">
                <div>
                    <i class="fas fa-hospital me-1"></i>
                    IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
                </div>
            </div>
        </footer>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="formToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS New Appointment] Initialized');

            // Initialize Flatpickr
            flatpickr("#appointment_date", {
                dateFormat: "Y-m-d",
                minDate: "today",
                maxDate: new Date().setDate(new Date().getDate() + 30),
                disable: [
                    function(date) {
                        return date.getDay() === 0 || date.getDay() === 6;
                    }
                ],
                prevArrow: '<i class="fas fa-arrow-left"></i>',
                nextArrow: '<i class="fas fa-arrow-right"></i>'
            });

            // Form submission
            const form = document.getElementById('appointmentForm');
            const toastEl = document.getElementById('formToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    toastEl.classList.remove('success', 'error');
                    toastEl.classList.add('error');
                    toastBody.textContent = 'Please fill in all required fields correctly.';
                    toast.show();
                    return;
                }

                // Phone validation
                const phone = document.getElementById('phone').value;
                if (!/^\d{10,11}$/.test(phone)) {
                    toastEl.classList.remove('success', 'error');
                    toastEl.classList.add('error');
                    toastBody.textContent = 'Phone number must be 10 or 11 digits.';
                    toast.show();
                    return;
                }

                // AJAX submission
                $.ajax({
                    url: form.action,
                    method: form.method,
                    data: $(form).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        console.log('[SSCMS New Appointment] AJAX Success:', response);
                        if (response.success) {
                            toastEl.classList.remove('error');
                            toastEl.classList.add('success');
                            toastBody.textContent = 'Appointment booked successfully!';
                            toast.show();
                            form.reset();
                            form.classList.remove('was-validated');
                            setTimeout(() => {
                                window.location.href = '/SSCMS/appointments/appointment-list.php';
                            }, 1500);
                        } else {
                            toastEl.classList.remove('success');
                            toastEl.classList.add('error');
                            toastBody.textContent = response.message || 'Failed to book appointment.';
                            toast.show();
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('[SSCMS New Appointment] AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                        toastEl.classList.remove('success');
                        toastEl.classList.add('error');
                        toastBody.textContent = 'Error: ' + (jqXHR.responseText || textStatus);
                        toast.show();
                    }
                });
            });
        });
    </script>
</body>
</html>