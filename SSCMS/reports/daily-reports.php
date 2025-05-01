<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check user session (commented out for testing)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }

// Get filter inputs
$filter_student = filter_input(INPUT_GET, 'student', FILTER_SANITIZE_STRING) ?? '';
$filter_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?? '';
$filter_time = filter_input(INPUT_GET, 'time', FILTER_SANITIZE_STRING) ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Daily Report">
    <meta name="author" content="ICCB">
    <title>Daily Report - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
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

        .btn-export {
            background-color: var(--success);
            border-color: var(--success);
            color: white;
        }

        .btn-export:hover {
            background-color: var(--success-dark);
            border-color: var(--success-dark);
            color: white;
        }

        .btn-print {
            background-color: var(--warning);
            border-color: var(--warning);
            color: var(--text-primary);
        }

        .btn-print:hover {
            background-color: var(--warning-dark);
            border-color: var(--warning-dark);
            color: var(--text-primary);
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

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }

        .modal-lg {
            max-width: 600px;
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
        }

        .clinic-footer {
            margin-top: 1.5rem;
            padding: 1rem 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
            border-top: 1px solid var(--border);
        }

        .report-header {
            text-align: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .report-header img {
            max-width: 100%;
            margin-bottom: 0.5rem;
        }

        .report-header h2 {
            color: var(--text-primary);
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .report-header p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.9rem;
        }

        .flatpickr-calendar {
            font-size: 0.9rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .flatpickr-day.selected {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        @media print {
            .btn, .card-header, .modal, .toast-container {
                display: none;
            }
            .container-fluid, .card-body {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .report-table {
                border: 1px solid #000;
            }
            .report-header {
                box-shadow: none;
                border-radius: 0;
            }
            .report-header img {
                max-width: 100%;
                margin-bottom: 0.5rem;
            }
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
                        <i class="fas fa-chart-line"></i>
                        Daily Report
                    </h1>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Daily Report</li>
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
                </div>

                <!-- Report Card -->
                <div class="card fade-in">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-file-alt me-1"></i>
                            Clinic Daily Report
                        </div>
                        <div>
                            <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <button class="btn btn-export btn-sm me-2" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i> Export as PDF
                            </button>
                            <button class="btn btn-print btn-sm" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                            <a href="../dashboard.php" class="text-decoration-none ms-2">
                                <img src="../assets/img/ICCLOGO.png" style="height: 20px;" alt="ICCB Logo">
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="report-header">
                            <img src="../assets/img/ICC_Banner.png" alt="Immaculate Conception College of Balayan, Inc. Banner" style="max-width: 800px;">
                            <h2>Clinic Daily Report</h2>
                            <p><strong>Date:</strong> <?= date('F j, Y') ?></p>
                        </div>
                        <div class="table-responsive">
                            <table id="reportTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Category</th>
                                        <th>Reason</th>
                                        <th>Visit Date</th>
                                        <th>Visit Time</th>
                                        <th>Medicine Taken</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Filter Modal -->
        <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="filterModalLabel">Filter Daily Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="filterForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterStudent" class="form-label">Student Name</label>
                                        <input type="text" id="filterStudent" class="form-control" placeholder="Search by student name" value="<?= htmlspecialchars($filter_student) ?>">
                                        <div class="form-text">Enter first or last name</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterDate" class="form-label">Date</label>
                                        <input type="text" id="filterDate" class="form-control flatpickr" placeholder="Select date" value="<?= htmlspecialchars($filter_date) ?>">
                                        <div class="form-text">Select visit date</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterTime" class="form-label">Time (7:00 AM - 4:00 PM)</label>
                                        <select id="filterTime" class="form-select">
                                            <option value="">Select time</option>
                                            <?php
                                            for ($hour = 7; $hour <= 16; $hour++) {
                                                $time_24 = sprintf("%02d:00", $hour);
                                                $time_12 = date("h:i A", strtotime("$time_24:00"));
                                                $selected = ($filter_time === $time_24) ? 'selected' : '';
                                                echo "<option value=\"$time_24\" $selected>$time_12</option>";
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">Select visit time</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                    <i class="fas fa-filter"></i> Apply Filters
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
        $(document).ready(function() {
            // Initialize Flatpickr
            flatpickr("#filterDate", {
                dateFormat: "Y-m-d",
                maxDate: new Date(),
                theme: "light",
                prevArrow: '<i class="fas fa-arrow-left"></i>',
                nextArrow: '<i class="fas fa-arrow-right"></i>'
            });

            // Initialize DataTable
            const reportTable = $('#reportTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[4, 'desc'], [5, 'desc']],
                language: { search: "", searchPlaceholder: "Search reports..." },
                columnDefs: [{ orderable: false, targets: [6, 7] }],
                serverSide: false,
                ajax: {
                    url: 'fetch_reports.php',
                    type: 'GET',
                    data: function(d) {
                        d.student = $('#filterStudent').val();
                        d.date = $('#filterDate').val();
                        d.time = $('#filterTime').val();
                        console.log('Sending filters:', d);
                    },
                    dataSrc: function(json) {
                        console.log('Received JSON:', json);
                        if (!json.data) {
                            console.error('No data in JSON response');
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('AJAX error:', error, thrown);
                        alert('Error fetching reports. Check console for details.');
                    }
                },
                columns: [
                    { data: 'last_name' },
                    { data: 'first_name' },
                    { data: 'category' },
                    { data: 'reason' },
                    { data: 'visit_date' },
                    { data: 'visit_time' },
                    { data: 'medicine_name', render: function(data) { return data || 'None'; } },
                    { data: 'medicine_quantity', render: function(data) { return data || '-'; } }
                ]
            });

            // Apply Filters
            window.applyFilters = function() {
                console.log('Applying filters');
                reportTable.ajax.reload();
                bootstrap.Modal.getInstance(document.getElementById('filterModal')).hide();

                // Update URL
                const url = new URL(window.location);
                const student = $('#filterStudent').val();
                const date = $('#filterDate').val();
                const time = $('#filterTime').val();
                if (student) url.searchParams.set('student', student);
                else url.searchParams.delete('student');
                if (date) url.searchParams.set('date', date);
                else url.searchParams.delete('date');
                if (time) url.searchParams.set('time', time);
                else url.searchParams.delete('time');
                window.history.pushState({}, '', url);
            };

            // Export to PDF
            window.exportToPDF = function() {
                const element = document.querySelector('.card-body');
                const opt = {
                    margin: 0.5,
                    filename: 'clinic_daily_report.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'mm', format: [216, 279], orientation: 'portrait' }
                };
                html2pdf().from(element).set(opt).save();
            };

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');

            // Apply initial filters if present
            if ('<?= $filter_student ?>' || '<?= $filter_date ?>' || '<?= $filter_time ?>') {
                reportTable.ajax.reload();
            }
        });
    </script>
</body>
</html>