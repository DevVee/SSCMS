<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Appointment List] Unauthorized access: no session");
    header('Location: /SSCMS/login.php');
    exit;
}

// Handle filters
$selected_date = isset($_GET['selected_date']) ? filter_var($_GET['selected_date'], FILTER_SANITIZE_STRING) : '';
$status_filter = isset($_GET['status']) ? filter_var($_GET['status'], FILTER_SANITIZE_STRING) : '';

// Build query
$query = "
    SELECT id, patient_name, category, phone, appointment_date, appointment_time, reason, status, appointee
    FROM appointments
    WHERE 1=1
";
$params = [];
if ($selected_date) {
    $query .= " AND DATE(appointment_date) = ?";
    $params[] = $selected_date;
}
if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}
$query .= " ORDER BY appointment_date DESC, appointment_time ASC";

try {
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Appointment List] Fetched " . count($appointments) . " appointments");
} catch (Exception $e) {
    error_log("[SSCMS Appointment List] Query error: " . $e->getMessage());
    $appointments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System">
    <meta name="author" content="ICCB">
    <title>Appointments List - SSCMS</title>
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

        .table {
            font-size: 0.8rem;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
        }

        .table th {
            background: var(--card-bg);
            color: var(--text-secondary);
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }

        .table td {
            padding: 0.5rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-hover tbody tr:hover {
            background: var(--primary-light);
        }

        .form-control, .form-select {
            font-size: 0.85rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text-primary);
            height: 32px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        .btn {
            font-size: 0.8rem;
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            height: 32px;
            line-height: 1.5;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca, #3730a3);
            transform: scale(1.05);
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

        .btn-success {
            background: var(--gradient-success);
            border: none;
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #22d3ee, #0891b2);
            transform: scale(1.05);
        }

        .btn-danger {
            background: var(--gradient-danger);
            border: none;
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: scale(1.05);
        }

        .status-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            color: white;
        }

        .bg-pending { background: var(--warning); }
        .bg-approved { background: var(--success); }
        .bg-rejected { background: var(--danger); }

        .breadcrumb {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }

        .modal-content {
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
            padding: 0.75rem 1rem;
        }

        .modal-body {
            padding: 1rem;
            font-size: 0.85rem;
        }

        .modal-body p {
            margin-bottom: 0.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 0.75rem 1rem;
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
        .delay-200 { animation-delay: 0.2s; opacity: 0; }

        @media (max-width: 992px) {
            :root { --sidebar-width: var(--sidebar-collapsed-width); }
            .content { margin-left: var(--sidebar-width); }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .dashboard-title { font-size: 1.1rem; }
            .table { font-size: 0.75rem; }
            .table th, .table td { padding: 0.4rem 0.5rem; }
            .table td:nth-child(3), .table th:nth-child(3) { display: none; } /* Phone */
            .table td:nth-child(4), .table th:nth-child(4) { display: none; } /* Category */
            .table td:nth-child(6), .table th:nth-child(6) { display: none; } /* Appointee */
            .form-control, .form-select { font-size: 0.8rem; height: 30px; }
            .btn { font-size: 0.75rem; height: 30px; padding: 0.3rem 0.6rem; }
            .modal-body { font-size: 0.8rem; }
            .modal-header, .modal-footer { padding: 0.5rem 0.75rem; }
            .status-badge { font-size: 0.7rem; padding: 0.2rem 0.5rem; }
        }

        @media print {
            .btn, .card-header, .modal, .toast-container, .breadcrumb { display: none; }
            .container-fluid, .card, .table { border: none; box-shadow: none; padding: 0; }
            .table { border: 1px solid #000; font-size: 0.75rem; }
            .content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <h1 class="dashboard-title slide-up">
                    <i class="fas fa-calendar-check me-2"></i>
                    Appointments List
                </h1>
                <nav aria-label="breadcrumb" class="slide-up delay-100">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/SSCMS/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Appointments</li>
                    </ol>
                </nav>

                <div class="card slide-up delay-100">
                    <div class="card-header">
                        <div>
                            <i class="fas fa-table me-2"></i>
                            All Appointments
                        </div>
                        <a href="/SSCMS/appointments/new-appointment.php" class="btn btn-sm btn-primary">New Appointment</a>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="sortTable('date')">Sort by Date</button>
                            <button class="btn btn-outline-primary btn-sm" onclick="sortTable('time')">Sort by Time</button>
                            <button class="btn btn-outline-primary btn-sm" onclick="sortTable('status')">Sort by Status</button>
                            <button class="btn btn-outline-primary btn-sm" onclick="showAll()">Show All</button>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dateFilterModal">Filter by Date</button>
                            <select id="statusFilter" class="form-select form-select-sm w-auto" onchange="applyStatusFilter()">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="appointmentTable">
                                <thead>
                                    <tr>
                                        <th onclick="sortTable('date')">Date</th>
                                        <th onclick="sortTable('time')">Time</th>
                                        <th>Phone</th>
                                        <th>Category</th>
                                        <th>Patient</th>
                                        <th>Appointee</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $row): ?>
                                        <?php
                                        $time_12 = date("h:i A", strtotime($row['appointment_time']));
                                        $hour = (int)substr($row['appointment_time'], 0, 2);
                                        if ($hour < 7 || $hour > 16) continue;
                                        ?>
                                        <tr class="appointment-row slide-up delay-200" 
                                            data-date="<?= htmlspecialchars($row['appointment_date']) ?>" 
                                            data-time="<?= htmlspecialchars($row['appointment_time']) ?>"
                                            data-status="<?= htmlspecialchars($row['status']) ?>"
                                            data-id="<?= $row['id'] ?>">
                                            <td><?= htmlspecialchars($row['appointment_date']) ?></td>
                                            <td><?= htmlspecialchars($time_12) ?></td>
                                            <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($row['category']) ?></td>
                                            <td><?= htmlspecialchars($row['patient_name']) ?></td>
                                            <td><?= htmlspecialchars($row['appointee']) ?></td>
                                            <td><?= htmlspecialchars($row['reason']) ?></td>
                                            <td>
                                                <span class="status-badge bg-<?= strtolower($row['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($row['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary view-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#actionModal"
                                                        data-id="<?= $row['id'] ?>"
                                                        data-patient="<?= htmlspecialchars($row['patient_name']) ?>"
                                                        data-phone="<?= htmlspecialchars($row['phone'] ?? 'N/A') ?>"
                                                        data-category="<?= htmlspecialchars($row['category']) ?>"
                                                        data-appointee="<?= htmlspecialchars($row['appointee']) ?>"
                                                        data-date="<?= htmlspecialchars($row['appointment_date']) ?>"
                                                        data-time="<?= htmlspecialchars($time_12) ?>"
                                                        data-reason="<?= htmlspecialchars($row['reason']) ?>"
                                                        data-status="<?= htmlspecialchars($row['status']) ?>">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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

    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">Appointment Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Patient:</strong> <span id="modalPatient"></span></p>
                    <p><strong>Category:</strong> <span id="modalCategory"></span></p>
                    <p><strong>Phone:</strong> <span id="modalPhone"></span></p>
                    <p><strong>Appointee:</strong> <span id="modalAppointee"></span></p>
                    <p><strong>Date:</strong> <span id="modalDate"></span></p>
                    <p><strong>Time:</strong> <span id="modalTime"></span></p>
                    <p><strong>Reason:</strong> <span id="modalReason"></span></p>
                    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                    <div id="actionButtons" class="btn-group w-100 mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dateFilterModal" tabindex="-1" aria-labelledby="dateFilterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dateFilterModalLabel">Filter by Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control flatpickr" id="filterDate" placeholder="Select Date">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyDateFilter">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="actionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
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
            console.log('[SSCMS Appointment List] Initialized');

            // Initialize Flatpickr
            flatpickr(".flatpickr", {
                dateFormat: "Y-m-d",
                minDate: "2025-01-01",
                maxDate: new Date().setFullYear(new Date().getFullYear() + 1),
                prevArrow: '<i class="fas fa-arrow-left"></i>',
                nextArrow: '<i class="fas fa-arrow-right"></i>'
            });

            // Sorting state
            let sortState = {
                date: 'desc',
                time: 'asc',
                status: 'asc'
            };

            // Sorting function
            window.sortTable = function(column) {
                const tbody = document.querySelector("#appointmentTable tbody");
                const rows = [...document.querySelectorAll(".appointment-row")];
                
                if (column === 'date') {
                    sortState.date = sortState.date === 'asc' ? 'desc' : 'asc';
                    rows.sort((a, b) => {
                        const dateA = new Date(a.dataset.date);
                        const dateB = new Date(b.dataset.date);
                        return sortState.date === 'asc' ? dateA - dateB : dateB - dateA;
                    });
                } else if (column === 'time') {
                    sortState.time = sortState.time === 'asc' ? 'desc' : 'asc';
                    rows.sort((a, b) => {
                        const timeA = a.dataset.time;
                        const timeB = b.dataset.time;
                        return sortState.time === 'asc' ? timeA.localeCompare(timeB) : timeB.localeCompare(timeA);
                    });
                } else if (column === 'status') {
                    sortState.status = sortState.status === 'asc' ? 'desc' : 'asc';
                    rows.sort((a, b) => {
                        const statusA = a.dataset.status.toLowerCase();
                        const statusB = b.dataset.status.toLowerCase();
                        return sortState.status === 'asc' ? statusA.localeCompare(statusB) : statusB.localeCompare(statusA);
                    });
                }

                rows.forEach(row => tbody.appendChild(row));
            };

            // Show all (reset filters)
            window.showAll = function() {
                const url = new URL(window.location);
                url.searchParams.delete('selected_date');
                url.searchParams.delete('status');
                window.location.href = url;
            };

            // Status filter
            window.applyStatusFilter = function() {
                const status = document.getElementById('statusFilter').value;
                const url = new URL(window.location);
                if (status) {
                    url.searchParams.set('status', status);
                } else {
                    url.searchParams.delete('status');
                }
                window.location.href = url;
            };

            // Date filter
            document.getElementById('applyDateFilter')?.addEventListener('click', function() {
                const selectedDate = document.getElementById('filterDate').value;
                if (selectedDate) {
                    const url = new URL(window.location);
                    url.searchParams.set('selected_date', selectedDate);
                    window.location.href = url;
                } else {
                    const toastEl = document.getElementById('actionToast');
                    const toastBody = toastEl.querySelector('.toast-body');
                    const toast = new bootstrap.Toast(toastEl);
                    toastEl.classList.remove('success');
                    toastEl.classList.add('error');
                    toastBody.textContent = 'Please select a date.';
                    toast.show();
                }
            });

            // Action modal
            const actionModal = document.getElementById('actionModal');
            const toastEl = document.getElementById('actionToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            actionModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const patient = button.getAttribute('data-patient');
                const category = button.getAttribute('data-category');
                const phone = button.getAttribute('data-phone');
                const appointee = button.getAttribute('data-appointee');
                const date = button.getAttribute('data-date');
                const time = button.getAttribute('data-time');
                const reason = button.getAttribute('data-reason');
                const status = button.getAttribute('data-status');

                document.getElementById('modalPatient').textContent = patient;
                document.getElementById('modalCategory').textContent = category;
                document.getElementById('modalPhone').textContent = phone;
                document.getElementById('modalAppointee').textContent = appointee;
                document.getElementById('modalDate').textContent = date;
                document.getElementById('modalTime').textContent = time;
                document.getElementById('modalReason').textContent = reason;
                document.getElementById('modalStatus').textContent = status;

                const actionButtons = document.getElementById('actionButtons');
                actionButtons.innerHTML = '';
                if (status.toLowerCase() === 'pending') {
                    actionButtons.innerHTML = `
                        <button class="btn btn-success approve-btn" data-id="${id}" data-action="approved">Approve</button>
                        <button class="btn btn-danger reject-btn" data-id="${id}" data-action="rejected">Reject</button>
                    `;
                }
            });

            // Handle action buttons
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('approve-btn') || event.target.classList.contains('reject-btn')) {
                    const id = event.target.getAttribute('data-id');
                    const action = event.target.getAttribute('data-action');
                    $.ajax({
                        url: '/SSCMS/appointments/update_appointment.php',
                        method: 'POST',
                        data: { 
                            id: id, 
                            status: action,
                            csrf_token: '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Appointment List] Action Success:', response);
                            if (response.success) {
                                toastEl.classList.remove('error');
                                toastEl.classList.add('success');
                                toastBody.textContent = `Appointment ${action} successfully!`;
                                toast.show();
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                toastEl.classList.remove('success');
                                toastEl.classList.add('error');
                                toastBody.textContent = response.message || `Failed to ${action} appointment.`;
                                toast.show();
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('[SSCMS Appointment List] Action Error:', textStatus, errorThrown, jqXHR.responseText);
                            toastEl.classList.remove('success');
                            toastEl.classList.add('error');
                            toastBody.textContent = 'Error: ' + (jqXHR.responseText || textStatus);
                            toast.show();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>