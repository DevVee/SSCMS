<?php
session_start();
require_once 'config/database.php';
require_once './includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /SSCMS/login.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Update session last_active
try {
    $stmt = $conn->prepare("UPDATE sessions SET last_active = NOW() WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$_SESSION['user_id'], session_id()]);
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Session update error: " . $e->getMessage());
}

// Fetch stats with retry
try {
    $total_patients = $conn->query("SELECT COUNT(DISTINCT patient_name) FROM appointments")->fetchColumn();
    $medicine_items = $conn->query("SELECT COUNT(*) FROM medicines")->fetchColumn();
    $low_stock_medicines = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity < 10")->fetchColumn();
    error_log("[SSCMS Dashboard] Stats fetched: patients=$total_patients, medicines=$medicine_items, low_stock=$low_stock_medicines");
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Stats query error: " . $e->getMessage());
    try {
        $total_patients = $conn->query("SELECT COUNT(DISTINCT patient_name) FROM appointments")->fetchColumn();
        $medicine_items = $conn->query("SELECT COUNT(*) FROM medicines")->fetchColumn();
        $low_stock_medicines = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity < 10")->fetchColumn();
    } catch (Exception $e2) {
        error_log("[SSCMS Dashboard] Stats retry error: " . $e2->getMessage());
        $total_patients = $medicine_items = $low_stock_medicines = 0;
    }
}

// Fetch pending appointments (today only)
try {
    $stmt = $conn->prepare("
        SELECT id, patient_name, category, appointment_date, appointment_time, status
        FROM appointments
        WHERE DATE(appointment_date) = CURDATE() AND LOWER(status) = 'pending'
        ORDER BY appointment_time ASC
    ");
    $stmt->execute();
    $pending_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Dashboard] Fetched " . count($pending_appointments) . " pending appointments");
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Pending appointments query error: " . $e->getMessage());
    $pending_appointments = [];
}

// Fetch approved appointments (today only)
try {
    $stmt = $conn->prepare("
        SELECT id, patient_name, category, appointment_date, appointment_time, status
        FROM appointments
        WHERE DATE(appointment_date) = CURDATE() AND LOWER(status) = 'approved'
        ORDER BY appointment_time ASC
    ");
    $stmt->execute();
    $approved_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Dashboard] Fetched " . count($approved_appointments) . " approved appointments");
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Approved appointments query error: " . $e->getMessage());
    $approved_appointments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Dashboard">
    <meta name="author" content="ICCB">
    <title>Dashboard - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
            line-height: 1.6;
            margin: 0;
            padding: 0;
            font-size: 0.95rem;
            font-weight: 400;
            overflow-x: hidden;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: calc(var(--header-height) + 1.5rem) 1.5rem 1rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="%23f9fafb"/><path d="M0 0L100 100M100 0L0 100" stroke="%23e5e7eb" stroke-width="0.1"/></svg>');
            background-size: 20px 20px;
        }

        .container-fluid {
            max-width: 1520px;
            padding: 0 1.5rem;
        }

        .welcome-banner {
            position: relative;
            background: var(--gradient-primary);
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            color: white;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 250px;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><path d="M160.8 0c-12.6 13.9-24.2 28.7-34.9 44.4-10.7 15.7-20.7 32.1-28.2 49.9-7.5 17.8-12.4 37.1-13.1 56.5h195.7V0H160.8z" fill="%23ffffff" fill-opacity="0.05"/></svg>');
            background-size: cover;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
            width: 70%;
        }

        .welcome-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .welcome-subtitle {
            font-size: 1rem;
            font-weight: 400;
            opacity: 0.9;
        }

        .date-display {
            position: absolute;
            right: 1.75rem;
            top: 1.75rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 120px;
            display: flex;
            align-items: center;
            border: 1px solid transparent;
        }

        .stat-card:hover {
            transform: scale(1.03);
            box-shadow: var(--hover-shadow);
            border-color: var(--primary);
        }

        .stat-card.patients { border-color: var(--success); }
        .stat-card.medicine { border-color: var(--warning); }
        .stat-card.low-stock { border-color: var(--danger); }

        .stat-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            margin-right: 1rem;
        }

        .stat-patients .stat-icon { background: var(--gradient-success); }
        .stat-medicine .stat-icon { background: var(--gradient-warning); }
        .stat-low-stock .stat-icon { background: var(--gradient-danger); }

        .stat-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
            white-space: nowrap;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-trend {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .trend-up { color: var(--success); }
        .trend-down { color: var(--danger); }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-left: 0.75rem;
            border-left: 4px solid var(--primary);
        }

        .actions-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.25rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
            height: 150px;
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .action-card:hover {
            transform: scale(1.05);
            box-shadow: var(--hover-shadow);
            border-color: var(--primary);
        }

        .action-card.log-patient:hover { border-color: var(--success); }
        .action-card.appointments:hover { border-color: var(--primary); }
        .action-card.reports:hover { border-color: var(--accent); }
        .action-card.inventory:hover { border-color: var(--warning); }

        .action-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .action-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-right: 1rem;
        }

        .action-card.log-patient .action-card-icon { background: var(--gradient-success); }
        .action-card.appointments .action-card-icon { background: var(--gradient-primary); }
        .action-card.reports .action-card-icon { background: var(--gradient-accent); }
        .action-card.inventory .action-card-icon { background: var(--gradient-warning); }

        .action-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .action-card-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .appointments-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }

        .appointments-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .appointments-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0;
            padding-left: 0.75rem;
            border-left: 4px solid var(--primary);
        }

        .appointments-pending .appointments-card-title { border-color: var(--warning); }
        .appointments-approved .appointments-card-title { border-color: var(--success); }

        .appointments-count {
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 16px;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .appointments-pending .appointments-count {
            background: var(--warning-light);
            color: var(--warning);
        }

        .appointments-approved .appointments-count {
            background: var(--success-light);
            color: var(--success);
        }

        .view-all-btn {
            background: var(--success);
            color: white;
            border-radius: 6px;
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .view-all-btn:hover {
            background: var(--primary-dark);
            color: white;
        }

        .appointments-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
        }

        .appointments-table th,
        .appointments-table td {
            padding: 0.75rem;
            text-align: left;
            vertical-align: middle;
        }

        .appointments-table th {
            font-weight: 500;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
        }

        .appointments-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.2s ease;
        }

        .appointments-table tbody tr:last-child { border-bottom: none; }
        .appointments-table tbody tr:hover { background: var(--primary-light); }

        .appointments-table.pending td:nth-child(2) { width: 120px; } /* Category */
        .appointments-table.pending td:nth-child(3) { width: 120px; } /* Time */
        .appointments-table.pending td:nth-child(4) { width: 120px; } /* Status */
        .appointments-table.pending td:nth-child(5) { width: 140px; } /* Action */

        .appointments-table.approved td:nth-child(2) { width: 120px; } /* Category */
        .appointments-table.approved td:nth-child(3) { width: 120px; } /* Time */
        .appointments-table.approved td:nth-child(4) { width: 120px; } /* Status */

        .badge {
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .badge.bg-warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .badge.bg-success {
            background: var(--success-light);
            color: var(--success);
        }

        .badge i {
            margin-right: 0.3rem;
            font-size: 0.75rem;
        }

        .badge.pulse {
            position: relative;
        }

        .badge.pulse::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            border-radius: 6px;
            animation: pulse 1.8s infinite;
            z-index: -1;
        }

        .badge.bg-warning.pulse::before { background: var(--warning); }
        .badge.bg-success.pulse::before { background: var(--success); }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            min-width: 120px;
            background: var(--success);
            color: white;
        }

        .action-btn.btn-approve {
            background: var(--success);
        }

        .action-btn.btn-reject {
            background: var(--danger);
        }

        .action-btn i {
            margin-right: 0.4rem;
            font-size: 0.85rem;
        }

        .empty-state {
            text-align: center;
            padding: 1.5rem 0;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--text-muted);
        }

        .empty-state-text {
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .toast-container {
            z-index: 9999;
        }

        .toast {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            border: none;
            min-width: 320px;
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
            font-weight: 500;
            font-size: 0.9rem;
        }

        footer {
            background: var(--card-bg);
            border-top: 1px solid var(--border);
            padding: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 1.5rem;
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes pulse {
            0% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0; transform: scale(1.3); }
            100% { opacity: 0.5; transform: scale(1); }
        }

        .fade-in { animation: fadeIn 0.5s ease forwards; }
        .slide-up { animation: slideInUp 0.5s ease forwards; }
        .delay-100 { animation-delay: 0.1s; opacity: 0; }
        .delay-200 { animation-delay: 0.2s; opacity: 0; }
        .delay-300 { animation-delay: 0.3s; opacity: 0; }
        .delay-400 { animation-delay: 0.4s; opacity: 0; }

        @media (max-width: 1280px) {
            .stats-container { grid-template-columns: repeat(2, 1fr); }
            .actions-container { grid-template-columns: 1fr; }
        }

        @media (max-width: 992px) {
            :root { --sidebar-width: var(--sidebar-collapsed-width); }
            .content { margin-left: var(--sidebar-width); }
            .stats-container { grid-template-columns: 1fr; }
            .welcome-content { width: 100%; }
            .date-display {
                position: static;
                margin-bottom: 1rem;
                display: inline-block;
            }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .welcome-banner { padding: 1.25rem; }
            .welcome-title { font-size: 1.5rem; }
            .welcome-subtitle { font-size: 0.95rem; }
            .stat-card, .action-card { height: auto; min-height: 120px; }
            .appointments-table th, .appointments-table td { padding: 0.5rem; font-size: 0.85rem; }
            .appointments-table td:nth-child(2) { display: none; } /* Category */
            .appointments-table.pending td:nth-child(3) { width: 100px; } /* Time */
            .appointments-table.pending td:nth-child(4) { display: none; } /* Status */
            .appointments-table.pending td:nth-child(5) { width: 120px; } /* Action */
            .appointments-table.approved td:nth-child(3) { width: 100px; } /* Time */
            .appointments-table.approved td:nth-child(4) { display: none; } /* Status */
            .action-btn { height: 34px; min-width: 100px; font-size: 0.85rem; }
            .view-all-btn { font-size: 0.8rem; padding: 0.3rem 0.6rem; }
        }
    </style>
</head>
<body>
    <?php include './includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Welcome Banner -->
                <div class="welcome-banner slide-up">
                    <div class="date-display">
                        <i class="far fa-calendar-alt me-2"></i>
                        <?= date('l, F j, Y') ?>
                    </div>
                    <div class="welcome-content">
                        <h2 class="welcome-title">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
                        <p class="welcome-subtitle">Your clinic dashboard is ready to roll!</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-container">
                    <div class="stat-card stat-patients patients slide-up delay-100">
                        <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Total Patients</div>
                            <div class="stat-value"><?= number_format($total_patients) ?></div>
                            <div class="stat-trend trend-up"><i class="fas fa-arrow-up me-1"></i> 12% from last month</div>
                        </div>
                    </div>
                    <div class="stat-card stat-medicine medicine slide-up delay-200">
                        <div class="stat-icon"><i class="fas fa-pills"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Medicine Items</div>
                            <div class="stat-value"><?= number_format($medicine_items) ?></div>
                            <div class="stat-trend trend-up"><i class="fas fa-arrow-up me-1"></i> 5% from last month</div>
                        </div>
                    </div>
                    <div class="stat-card stat-low-stock low-stock slide-up delay-300">
                        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Low Stock Items</div>
                            <div class="stat-value"><?= number_format($low_stock_medicines) ?></div>
                            <div class="stat-trend trend-down"><i class="fas fa-arrow-down me-1"></i> Needs attention</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <h3 class="section-title slide-up">Quick Actions</h3>
                <div class="actions-container">
                    <a href="./patients/log-new-patient.php" class="action-card log-patient slide-up delay-100">
                        <div class="action-card-icon"><i class="fas fa-calendar-plus"></i></div>
                        <div class="action-content">
                            <h5 class="action-card-title">Log Patient</h5>
                            <p class="action-card-text">Log a new patient</p>
                        </div>
                    </a>
                    <a href="./appointments/appointment-list.php" class="action-card appointments slide-up delay-200">
                        <div class="action-card-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="action-content">
                            <h5 class="action-card-title">Appointments</h5>
                            <p class="action-card-text">Manage visits and schedules</p>
                        </div>
                    </a>
                    <a href="./reports/daily-reports.php" class="action-card reports slide-up delay-300">
                        <div class="action-card-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="action-content">
                            <h5 class="action-card-title">Daily Report</h5>
                            <p class="action-card-text">View clinic stats</p>
                        </div>
                    </a>
                    <a href="./inventory/inventory.php" class="action-card inventory slide-up delay-400">
                        <div class="action-card-icon"><i class="fas fa-boxes"></i></div>
                        <div class="action-content">
                            <h5 class="action-card-title">Manage Inventory</h5>
                            <p class="action-card-text">Track medicine stock</p>
                        </div>
                    </a>
                </div>

                <!-- Approved Appointments -->
                <div class="appointments-card appointments-approved slide-up">
                    <div class="appointments-card-header">
                        <h3 class="appointments-card-title">Approved Appointments Today</h3>
                        <div>
                            <span class="appointments-count"><?= count($approved_appointments) ?> Approved</span>
                            <a href="/SSCMS/appointments/appointment-list.php" class="view-all-btn ms-2"><i class="fas fa-list me-1"></i> View All</a>
                        </div>
                    </div>
                    <?php if (empty($approved_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check empty-state-icon"></i>
                            <p class="empty-state-text">No approved appointments for today.</p>
                        </div>
                    <?php else: ?>
                        <table class="appointments-table approved">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Category</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_appointments as $appt): ?>
                                    <tr data-appointment-id="<?= $appt['id'] ?>">
                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars($appt['patient_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($appt['category']) ?></td>
                                        <td><?= $appt['appointment_time'] ? date('h:i A', strtotime($appt['appointment_time'])) : 'N/A' ?></td>
                                        <td><span class="badge bg-success pulse text-white"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($appt['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer class="fade-in">
            <div class="container-fluid">
                <div>
                    <i class="fas fa-hospital me-1"></i>
                    IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
                </div>
            </div>
        </footer>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="dashboardToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
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
            console.log('[SSCMS Dashboard] Initialized');

            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            const toastEl = document.getElementById('dashboardToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            function handleFormSubmit(form, successMessage, toastClass) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    $.ajax({
                        url: '/SSCMS/appointments/update_appointment.php',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Dashboard] Action Success:', response);
                            if (response.success) {
                                toastEl.classList.remove('success', 'error');
                                toastEl.classList.add(toastClass);
                                toastBody.textContent = successMessage;
                                toast.show();
                                const row = form.closest('tr');
                                const appointmentId = row.dataset.appointmentId;
                                const patientName = row.querySelector('td:first-child').textContent;
                                const category = row.querySelector('td:nth-child(2)').textContent;
                                const time = row.querySelector('td:nth-child(3)').textContent;
                                if (form.classList.contains('approve-form')) {
                                    row.remove();
                                    const approvedTableBody = document.querySelector('.appointments-approved tbody');
                                    if (approvedTableBody) {
                                        const newRow = document.createElement('tr');
                                        newRow.setAttribute('data-appointment-id', appointmentId);
                                        newRow.innerHTML = `
                                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${patientName}</td>
                                            <td>${category}</td>
                                            <td>${time}</td>
                                            <td><span class="badge bg-success pulse"><i class="fas fa-check-circle"></i> Approved</span></td>
                                        `;
                                        approvedTableBody.appendChild(newRow);
                                        document.querySelector('.appointments-approved .empty-state')?.remove();
                                    }
                                    if (!document.querySelector('.appointments-pending tbody tr')) {
                                        const emptyState = document.createElement('div');
                                        emptyState.className = 'empty-state';
                                        emptyState.innerHTML = `
                                            <i class="fas fa-calendar-times empty-state-icon"></i>
                                            <p class="empty-state-text">No pending appointments for today.</p>
                                        `;
                                        document.querySelector('.appointments-pending').appendChild(emptyState);
                                    }
                                }
                            } else {
                                toastEl.classList.remove('success', 'error');
                                toastEl.classList.add('error');
                                toastBody.textContent = response.message || 'Action failed.';
                                toast.show();
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('[SSCMS Dashboard] Action Error:', textStatus, errorThrown, jqXHR.responseText);
                            toastEl.classList.remove('success', 'error');
                            toastEl.classList.add('error');
                            toastBody.textContent = 'Error: ' + (jqXHR.responseText || textStatus);
                            toast.show();
                        }
                    });
                });
            }

            document.querySelectorAll('.approve-form').forEach(form => {
                handleFormSubmit(form, 'Appointment approved successfully!', 'success');
            });

            const tableRows = document.querySelectorAll('.appointments-table tbody tr');
            tableRows.forEach((row, index) => {
                row.classList.add('slide-up');
                row.style.animationDelay = `${index * 0.1 + 0.2}s`;
            });
        });
    </script>
</body>
</html>