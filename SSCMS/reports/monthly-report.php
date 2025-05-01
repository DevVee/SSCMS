<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Monthly Report] Unauthorized access: no session");
    header('Location: /SSCMS/login.php');
    exit;
}

// Initialize variables
$filter_month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?? '';
$filter_year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => date('Y')]]) ?? date('Y');
$month_name = empty($filter_month) ? 'All Months' : date('F', mktime(0, 0, 0, $filter_month, 1));
$error_message = '';
$success_message = '';

// Define categories consistent with manage-patients.php
$categories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'];

// Query for monthly visit reasons
try {
    $query_reasons = "
        SELECT DATE_FORMAT(v.visit_date, '%M') AS month_name, v.reason, COUNT(*) AS count
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_reasons .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_reasons .= " GROUP BY month_name, reason ORDER BY STR_TO_DATE(month_name, '%M') ASC LIMIT 5";
    $stmt_reasons = $conn->prepare($query_reasons);
    $stmt_reasons->execute($params);
    $result_reasons = $stmt_reasons->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Monthly Report] Fetched " . count($result_reasons) . " reasons");
} catch (Exception $e) {
    $error_message = "Failed to fetch visit reasons: " . $e->getMessage();
    error_log("[SSCMS Monthly Report] Error (Reasons): " . $e->getMessage());
    $result_reasons = [];
}

// Query for total visits per month
try {
    $query_total_visits = "
        SELECT DATE_FORMAT(v.visit_date, '%M') AS month_name, COUNT(*) AS total_visits
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_total_visits .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_total_visits .= " GROUP BY month_name ORDER BY STR_TO_DATE(month_name, '%M') ASC";
    $stmt_total_visits = $conn->prepare($query_total_visits);
    $stmt_total_visits->execute($params);
    $result_total_visits = $stmt_total_visits->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Monthly Report] Fetched " . count($result_total_visits) . " total visits");
} catch (Exception $e) {
    $error_message = "Failed to fetch total visits: " . $e->getMessage();
    error_log("[SSCMS Monthly Report] Error (Total Visits): " . $e->getMessage());
    $result_total_visits = [];
}

// Query for top reasons
try {
    $query_top_reasons = "
        SELECT v.reason, COUNT(*) AS count
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_top_reasons .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_top_reasons .= " GROUP BY v.reason ORDER BY count DESC LIMIT 5";
    $stmt_top_reasons = $conn->prepare($query_top_reasons);
    $stmt_top_reasons->execute($params);
    $result_top_reasons = $stmt_top_reasons->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Monthly Report] Fetched " . count($result_top_reasons) . " top reasons");
} catch (Exception $e) {
    $error_message = "Failed to fetch top reasons: " . $e->getMessage();
    error_log("[SSCMS Monthly Report] Error (Top Reasons): " . $e->getMessage());
    $result_top_reasons = [];
}

// Query for category-based analytics
try {
    $query_categories = "
        SELECT p.category, COUNT(*) AS count
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_categories .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_categories .= " GROUP BY p.category ORDER BY count DESC LIMIT 5";
    $stmt_categories = $conn->prepare($query_categories);
    $stmt_categories->execute($params);
    $result_categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Monthly Report] Fetched " . count($result_categories) . " categories");
} catch (Exception $e) {
    $error_message = "Failed to fetch categories: " . $e->getMessage();
    error_log("[SSCMS Monthly Report] Error (Categories): " . $e->getMessage());
    $result_categories = [];
}

// Query for top patients by visit frequency
try {
    $query_top_patients = "
        SELECT CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.category, COUNT(*) AS count
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_top_patients .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_top_patients .= " GROUP BY p.id, p.first_name, p.last_name, p.category ORDER BY count DESC LIMIT 3";
    $stmt_top_patients = $conn->prepare($query_top_patients);
    $stmt_top_patients->execute($params);
    $result_top_patients = $stmt_top_patients->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Monthly Report] Fetched " . count($result_top_patients) . " top patients");
} catch (Exception $e) {
    $error_message = "Failed to fetch top patients: " . $e->getMessage();
    error_log("[SSCMS Monthly Report] Error (Top Patients): " . $e->getMessage());
    $result_top_patients = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Monthly Report">
    <meta name="author" content="ICCB">
    <title>Monthly Report - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --primary-dark: #4338ca;
            --success: #2ec4b6;
            --success-light: #e6f7f5;
            --success-dark: #1aa396;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --warning-dark: #d97706;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --danger-dark: #dc2626;
            --accent: #8b5cf6;
            --accent-light: #ede9fe;
            --accent-dark: #7c3aed;
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
        }

        [data-theme="dark"] {
            --primary: #818cf8;
            --primary-light: #3730a3;
            --primary-dark: #4f46e5;
            --success: #4dd4c7;
            --success-light: #134e4a;
            --success-dark: #2ec4b6;
            --warning: #fb923c;
            --warning-light: #78350f;
            --warning-dark: #f59e0b;
            --danger: #f87171;
            --danger-light: #7f1d1d;
            --danger-dark: #ef4444;
            --accent: #a78bfa;
            --accent-light: #4c1d95;
            --accent-dark: #8b5cf6;
            --secondary: #9ca3af;
            --text-primary: #e5e7eb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border: #4b5563;
            --background: #111827;
            --card-bg: #1f2937;
        }

        html {
            transition: all var(--transition-speed) ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            padding-top: calc(var(--header-height) + 1.5rem);
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1400px;
            padding: 0 1.5rem;
        }

        .report-header {
            text-align: center;
            padding: 1.5rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            animation: fadeInUp 0.5s ease;
        }

        .report-header img {
            max-width: 100%;
            width: 600px;
            margin-bottom: 0.75rem;
        }

        .report-header h2 {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .report-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 400;
            margin: 0;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.5s ease;
        }

        .dashboard-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dashboard-title i {
            color: var(--primary);
            font-size: 1.2rem;
            background: var(--primary-light);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-breadcrumb {
            padding: 0.75rem 1rem;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.5s ease;
        }

        .custom-breadcrumb .breadcrumb-item {
            color: var(--text-secondary);
            font-weight: 400;
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 500;
        }

        .summary-card {
            background: linear-gradient(135deg, #4f46e5, #818cf8);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: fadeInUp 0.5s ease;
        }

        .summary-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .summary-card .stat {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .summary-card .label {
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.9;
        }

        .summary-card i {
            font-size: 1rem;
            margin-right: 0.5rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.5s ease;
        }

        .card-header {
            background: var(--gradient-primary);
            color: white;
            border-bottom: none;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 1rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.3rem;
        }

        .chart-container {
            padding: 0;
            background: transparent;
            border: none;
            margin-bottom: 0;
        }

        .chart-wrapper {
            padding: 1rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: scaleIn 0.5s ease;
        }

        .plotly-chart {
            width: 100%;
            height: 400px !important;
            display: block;
            animation: scaleIn 0.5s ease;
        }

        .table {
            color: var(--text-primary);
            font-size: 0.85rem;
            margin-bottom: 0;
        }

        .table th, .table td {
            padding: 0.5rem;
            border-color: var(--border);
            vertical-align: middle;
        }

        .table th {
            font-weight: 500;
            background: var(--primary-light);
            color: var(--text-primary);
        }

        .table tbody tr:hover {
            background: var(--primary-light);
        }

        .btn {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all var(--transition-speed);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-success {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: var(--success-dark);
            border-color: var(--success-dark);
        }

        .btn-warning {
            background: var(--warning);
            border-color: var(--warning);
            color: var(--text-primary);
        }

        .btn-warning:hover {
            background: var(--warning-dark);
            border-color: var(--warning-dark);
        }

        .form-select, .form-control {
            border-radius: 8px;
            border: 1px solid var(--border);
            padding: 0.5rem;
            font-size: 0.85rem;
            height: 38px;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        .clinic-footer {
            margin-top: 2rem;
            padding: 1rem 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.85rem;
            border-top: 1px solid var(--border);
            animation: fadeInUp 0.5s ease;
        }

        .fade-in-up {
            animation: fadeInUp 0.5s ease;
        }

        .scale-in {
            animation: scaleIn 0.5s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        @media print, pdf {
            @page {
                size: A4;
                margin: 1cm;
            }
            body {
                font-family: 'Poppins', sans-serif;
                font-size: 8pt;
                background: #ffffff;
                color: #000000;
                margin: 0;
                padding: 0;
                width: 210mm;
                height: 297mm;
                box-sizing: border-box;
            }
            .content, .container-fluid, .card, .card-body {
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: none !important;
                width: 100% !important;
                max-width: 190mm !important;
            }
            .dashboard-header, .custom-breadcrumb, .btn, .toast-container, nav, .summary-card h4, .chart-container {
                display: none !important;
            }
            .report-header {
                padding: 0.3cm 0;
                text-align: center;
                border-bottom: 1px solid #000;
                margin-bottom: 0.3cm;
            }
            .report-header img {
                width: 6cm;
                margin-bottom: 0.2cm;
            }
            .report-header h2 {
                font-size: 12pt;
                font-weight: 600;
                margin: 0;
                color: #000;
            }
            .report-header p {
                font-size: 10pt;
                font-weight: 400;
                margin: 0;
                color: #000;
            }
            .summary-card {
                background: none;
                color: #000;
                padding: 0.3cm;
                margin-bottom: 0.3cm;
                border: 1px solid #000;
                display: flex !important;
                flex-wrap: wrap;
                gap: 0.5cm;
            }
            .summary-card .col-md-4 {
                flex: 1 1 30%;
                padding: 0.1cm;
            }
            .summary-card .stat {
                font-size: 12pt;
                font-weight: 600;
                margin-bottom: 0.1cm;
            }
            .summary-card .label {
                font-size: 8pt;
                font-weight: 400;
            }
            .summary-card i {
                font-size: 8pt;
                margin-right: 0.1cm;
            }
            .chart-wrapper {
                border: 1px solid #000;
                padding: 0.2cm;
                margin-bottom: 0.3cm;
                page-break-inside: avoid;
            }
            .chart-wrapper h5 {
                font-size: 10pt;
                font-weight: 600;
                margin: 0 0 0.2cm 0;
                color: #000;
            }
            .plotly-chart {
                height: 4cm !important;
                width: 100% !important;
                margin: 0 !important;
            }
            .table-section {
                margin-bottom: 0.3cm;
                page-break-inside: avoid;
            }
            .table-section h5 {
                font-size: 10pt;
                font-weight: 600;
                margin: 0 0 0.2cm 0;
                color: #000;
                border-bottom: 1px solid #000;
                padding-bottom: 0.1cm;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                font-size: 8pt;
                margin: 0;
                page-break-inside: avoid;
            }
            .table th, .table td {
                border: 1px solid #000;
                padding: 0.1cm;
                text-align: left;
            }
            .table th {
                background: #e0f2fe;
                font-weight: 600;
                color: #000;
            }
            .table-responsive {
                overflow: visible;
                margin: 0;
            }
            .clinic-footer {
                display: block !important;
                position: absolute;
                bottom: 0.5cm;
                width: 100%;
                text-align: center;
                font-size: 8pt;
                color: #000;
                border-top: 1px solid #000;
                padding-top: 0.1cm;
                margin: 0;
            }
            .section-divider {
                border-top: 1px solid #000;
                margin: 0.3cm 0;
            }
        }

        @media (max-width: 992px) {
            :root {
                --sidebar-width: var(--sidebar-collapsed-width);
            }
            .content {
                margin-left: var(--sidebar-width);
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            .plotly-chart {
                height: 350px !important;
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }
            .container-fluid {
                padding: 0 1rem;
            }
            .report-header img {
                width: 400px;
            }
            .report-header h2 {
                font-size: 1.2rem;
            }
            .report-header p {
                font-size: 0.85rem;
            }
            .dashboard-title {
                font-size: 1.2rem;
            }
            .dashboard-title i {
                font-size: 1rem;
                width: 32px;
                height: 32px;
            }
            .summary-card .stat {
                font-size: 1.2rem;
            }
            .summary-card .label {
                font-size: 0.8rem;
            }
            .plotly-chart {
                height: 300px !important;
            }
            .table {
                font-size: 0.8rem;
            }
            .table th, .table td {
                padding: 0.4rem;
            }
            .btn, .form-select, .form-control {
                font-size: 0.8rem;
                padding: 0.4rem;
                height: 34px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Report Header -->
                <div class="report-header fade-in-up">
                    <img src="../assets/img/ICC_Banner.png" alt="Immaculate Conception College of Balayan, Inc. Banner" onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
                    <h2>Clinic Monthly Report</h2>
                    <p>Period: <?= htmlspecialchars($month_name) ?> <?= htmlspecialchars($filter_year) ?></p>
                </div>

                <!-- Dashboard Header -->
                <div class="dashboard-header fade-in-up">
                    <h1 class="dashboard-title">
                        <i class="fas fa-chart-bar"></i>
                        Monthly Report
                    </h1>
                    <div class="d-flex gap-2">
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <select name="month" id="monthFilter" class="form-select">
                                <option value="">All Months</option>
                                <?php
                                for ($m = 1; $m <= 12; $m++) {
                                    $selected = ($filter_month == $m) ? 'selected' : '';
                                    echo "<option value=\"$m\" $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                                }
                                ?>
                            </select>
                            <input type="number" name="year" id="yearFilter" class="form-control" value="<?= htmlspecialchars($filter_year) ?>" min="2000" max="<?= date('Y') ?>" style="width: 120px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        </form>
                    </div>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in-up">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Monthly Report</li>
                    </ol>
                </nav>

                <!-- Toast Container -->
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <?php if ($error_message): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($error_message) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($success_message) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Summary Card -->
                <div class="summary-card fade-in-up">
                    <h4><i class="fas fa-info-circle"></i> Summary for <?= htmlspecialchars($month_name) ?> <?= htmlspecialchars($filter_year) ?></h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat"><i class="fas fa-users"></i> <?= array_sum(array_column($result_total_visits, 'total_visits')) ?></div>
                            <div class="label">Total Visits</div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat"><i class="fas fa-user"></i> <?= count($result_top_patients) ? $result_top_patients[0]['count'] : 0 ?></div>
                            <div class="label">Highest Patient Visits</div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat"><i class="fas fa-stethoscope"></i> <?= count($result_top_reasons) ? htmlspecialchars($result_top_reasons[0]['reason']) : 'N/A' ?></div>
                            <div class="label">Top Reason</div>
                        </div>
                    </div>
                </div>

                <!-- Main Report Card -->
                <div class="card fade-in-up">
                    <div class="card-header">
                        <span><i class="fas fa-file-alt me-2"></i> Clinic Analytics</span>
                        <div>
                            <button type="button" class="btn btn-success btn-sm me-2" onclick="exportToPDF()"><i class="fas fa-file-pdf me-1"></i> PDF</button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Graphs Section -->
                        <div class="chart-container">
                            <div class="section-header">Graphical Analytics</div>
                            <div class="row">
                                <!-- Total Visits Chart -->
                                <div class="col-md-6">
                                    <div class="chart-wrapper">
                                        <h5>Total Visits per Month</h5>
                                        <?php if (empty($result_total_visits)): ?>
                                            <p class="text-muted">No visit data available for this period.</p>
                                        <?php else: ?>
                                            <div id="totalVisitsChart" class="plotly-chart"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Category Pie Chart -->
                                <div class="col-md-6">
                                    <div class="chart-wrapper">
                                        <h5>Category Visit Distribution</h5>
                                        <?php if (empty($result_categories)): ?>
                                            <p class="text-muted">No visit data available for this period.</p>
                                        <?php else: ?>
                                            <div id="categoryPieChart" class="plotly-chart"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Patient Pie Chart -->
                                <div class="col-md-12">
                                    <div class="chart-wrapper">
                                        <h5>Top Patients Visit Distribution</h5>
                                        <?php if (empty($result_top_patients)): ?>
                                            <p class="text-muted">No patient visit data available for this period.</p>
                                        <?php else: ?>
                                            <div id="patientPieChart" class="plotly-chart"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="section-divider"></div>
                        </div>

                        <!-- Tables Section -->
                        <div class="chart-container">
                            <div class="section-header">Detailed Reports</div>
                            <!-- Monthly Visit Reasons Table -->
                            <div class="table-section">
                                <h5>Visit Reasons by Month</h5>
                                <?php if (empty($result_reasons)): ?>
                                    <p class="text-muted">No visit data available for this period.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Month</th>
                                                    <th>Reason</th>
                                                    <th>Visits</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($result_reasons as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['month_name']) ?></td>
                                                        <td><?= htmlspecialchars($row['reason']) ?></td>
                                                        <td><?= htmlspecialchars($row['count']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Top Reasons Table -->
                            <div class="table-section">
                                <h5>Top 5 Reasons for Visits</h5>
                                <?php if (empty($result_top_reasons)): ?>
                                    <p class="text-muted">No visit data available for this period.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Reason</th>
                                                    <th>Visits</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($result_top_reasons as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['reason']) ?></td>
                                                        <td><?= htmlspecialchars($row['count']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Category Table -->
                            <div class="table-section">
                                <h5>Visits by Category</h5>
                                <?php if (empty($result_categories)): ?>
                                    <p class="text-muted">No visit data available for this period.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th>Visits</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($result_categories as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['category']) ?></td>
                                                        <td><?= htmlspecialchars($row['count']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Top Patients Table -->
                            <div class="table-section">
                                <h5>Top 3 Patients by Visits</h5>
                                <?php if (empty($result_top_patients)): ?>
                                    <p class="text-muted">No patient visit data available for this period.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Category</th>
                                                    <th>Visits</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($result_top_patients as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['patient_name']) ?></td>
                                                        <td><?= htmlspecialchars($row['category']) ?></td>
                                                        <td><?= htmlspecialchars($row['count']) ?></td>
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

        <footer class="clinic-footer fade-in-up">
            <div class="container-fluid">
                <p class="mb-0">Clinic Management System © 2025 ICCB. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize toasts
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                new bootstrap.Toast(toast, { delay: 5000 }).show();
            });

            // Debug Plotly
            console.log('Plotly loaded:', typeof Plotly !== 'undefined');
            console.log('Total Visits Data:', <?php echo json_encode($result_total_visits); ?>);
            console.log('Category Data:', <?php echo json_encode($result_categories); ?>);
            console.log('Patient Data:', <?php echo json_encode($result_top_patients); ?>);

            // Plotly configuration
            const chartColors = [
                '#4f46e5', // primary
                '#2ec4b6', // success
                '#f59e0b', // warning
                '#ef4444', // danger
                '#8b5cf6', // accent
                '#6b7280'  // secondary
            ];

            // Total Visits Chart (Bar)
            const totalVisitsData = [{
                x: [],
                y: [],
                type: 'bar',
                marker: {
                    color: chartColors[0],
                    line: { color: '#4338ca', width: 1 }
                },
                text: [],
                textposition: 'auto',
                textfont: { size: 12, color: '#ffffff' },
                hoverinfo: 'x+y',
                hovertemplate: '%{x}: %{y} visits<extra></extra>'
            }];
            <?php foreach ($result_total_visits as $row): ?>
                totalVisitsData[0].x.push('<?= addslashes($row['month_name']) ?>');
                totalVisitsData[0].y.push(<?= $row['total_visits'] ?>);
                totalVisitsData[0].text.push(<?= $row['total_visits'] ?>);
            <?php endforeach; ?>

            const totalVisitsLayout = {
                height: 400,
                margin: { t: 30, b: 100, l: 60, r: 30 },
                xaxis: {
                    title: 'Month',
                    tickfont: { size: 12 },
                    tickangle: 45
                },
                yaxis: {
                    title: 'Visits',
                    tickfont: { size: 12 },
                    rangemode: 'tozero',
                    fixedrange: true
                },
                showlegend: false,
                paper_bgcolor: 'rgba(0,0,0,0)',
                plot_bgcolor: 'rgba(0,0,0,0)',
                font: { family: 'Poppins, sans-serif', color: '#1f2937' },
                bargap: 0.2
            };

            const totalVisitsDiv = document.getElementById('totalVisitsChart');
            if (totalVisitsDiv) {
                if (totalVisitsData[0].x.length > 0 && totalVisitsData[0].y.length > 0) {
                    console.log('Rendering Total Visits Chart:', totalVisitsData);
                    Plotly.newPlot(totalVisitsDiv, totalVisitsData, totalVisitsLayout, {
                        displayModeBar: false,
                        responsive: true,
                        scrollZoom: true
                    });
                } else {
                    console.warn('Total Visits Chart: No data to render');
                    totalVisitsDiv.innerHTML = '<p class="text-muted">No visit data available for this period.</p>';
                }
            } else {
                console.error('Total Visits Chart: Div element not found');
            }

            // Category Pie Chart
            const categoryData = [{
                labels: [],
                values: [],
                type: 'pie',
                marker: { colors: chartColors },
                textinfo: 'label+percent',
                textfont: { size: 12 },
                textposition: 'inside',
                hoverinfo: 'label+value+percent',
                pull: [0.1, 0, 0, 0, 0],
                rotation: 45,
                direction: 'clockwise'
            }];
            <?php foreach ($result_categories as $row): ?>
                categoryData[0].labels.push('<?= addslashes($row['category']) ?>');
                categoryData[0].values.push(<?= $row['count'] ?>);
            <?php endforeach; ?>

            const categoryLayout = {
                height: 400,
                margin: { t: 30, b: 50, l: 30, r: 30 },
                showlegend: true,
                legend: {
                    x: 0.5,
                    y: -0.1,
                    xanchor: 'center',
                    yanchor: 'top',
                    orientation: 'h',
                    font: { size: 12 }
                },
                paper_bgcolor: 'rgba(0,0,0,0)',
                font: { family: 'Poppins, sans-serif', color: '#1f2937' }
            };

            const categoryPieDiv = document.getElementById('categoryPieChart');
            if (categoryPieDiv) {
                if (categoryData[0].labels.length > 0 && categoryData[0].values.length > 0) {
                    console.log('Rendering Category Pie Chart:', categoryData);
                    Plotly.newPlot(categoryPieDiv, categoryData, categoryLayout, {
                        responsive: true,
                        displayModeBar: false
                    });
                } else {
                    console.warn('Category Pie Chart: No data to render');
                    categoryPieDiv.innerHTML = '<p class="text-muted">No visit data available for this period.</p>';
                }
            } else {
                console.error('Category Pie Chart: Div element not found');
            }

            // Patient Pie Chart
            const patientData = [{
                labels: [],
                values: [],
                type: 'pie',
                marker: { colors: chartColors.slice(0, 3) },
                textinfo: 'label+percent',
                textfont: { size: 12 },
                textposition: 'inside',
                hoverinfo: 'label+value+percent',
                pull: [0.1, 0, 0],
                rotation: 45,
                direction: 'clockwise'
            }];
            <?php foreach ($result_top_patients as $row): ?>
                patientData[0].labels.push('<?= addslashes($row['patient_name'] . ' (' . $row['category'] . ')') ?>');
                patientData[0].values.push(<?= $row['count'] ?>);
            <?php endforeach; ?>

            const patientLayout = {
                height: 400,
                margin: { t: 30, b: 50, l: 30, r: 30 },
                showlegend: true,
                legend: {
                    x: 0.5,
                    y: -0.1,
                    xanchor: 'center',
                    yanchor: 'top',
                    orientation: 'h',
                    font: { size: 12 }
                },
                paper_bgcolor: 'rgba(0,0,0,0)',
                font: { family: 'Poppins, sans-serif', color: '#1f2937' }
            };

            const patientPieDiv = document.getElementById('patientPieChart');
            if (patientPieDiv) {
                if (patientData[0].labels.length > 0 && patientData[0].values.length > 0) {
                    console.log('Rendering Patient Pie Chart:', patientData);
                    Plotly.newPlot(patientPieDiv, patientData, patientLayout, {
                        responsive: true,
                        displayModeBar: false
                    });
                } else {
                    console.warn('Patient Pie Chart: No data to render');
                    patientPieDiv.innerHTML = '<p class="text-muted">No patient visit data available for this period.</p>';
                }
            } else {
                console.error('Patient Pie Chart: Div element not found');
            }

            // Export to PDF
            window.exportToPDF = function() {
                const element = document.querySelector('.card-body');
                if (!element) {
                    console.error('PDF Export: Card body not found');
                    return;
                }
                const opt = {
                    margin: [10, 10, 10, 10],
                    filename: `clinic_monthly_report_${'<?= $month_name ?>'}_${'<?= $filter_year ?>'}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 3, useCORS: true },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'], avoid: ['.chart-wrapper', '.table-section'] }
                };
                html2pdf().from(element).set(opt).save().catch(err => {
                    console.error('PDF Export Error:', err);
                });
            };

            // Validate year input
            const yearInput = document.getElementById('yearFilter');
            if (yearInput) {
                yearInput.addEventListener('input', function() {
                    const min = parseInt(this.min);
                    const max = parseInt(this.max);
                    if (this.value < min) this.value = min;
                    if (this.value > max) this.value = max;
                });
            }
        });
    </script>
</body>
</html>