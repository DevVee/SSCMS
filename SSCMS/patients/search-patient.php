```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check user session (uncomment for production)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }

// Fetch patients based on search
$patients = [];
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';

$query = "SELECT * FROM patients WHERE 1=1";
$params = [];

if ($searchTerm) {
    $query .= " AND (last_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ?)";
    $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
}

$query .= " ORDER BY last_name, first_name, middle_name";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0 líder
    <meta name="description" content="Clinic Management System - Search Patients">
    <meta name="author" content="ICCB">
    <title>Search Patients - Clinic Management</title>
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
            --sidebar-width: 200px;
            --sidebar-collapsed-width: 50px;
            --header-height: 50px;
            --card-border-radius: 12px;
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
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: none;
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
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

        .btn-outline-secondary {
            border-color: var(--secondary);
            color: var(--secondary);
        }

        .btn-outline-secondary:hover {
            background-color: #e5e7eb;
            border-color: var(--secondary-dark);
            color: var(--secondary-dark);
        }

        .btn i {
            margin-right: 0.4rem;
        }

        .search-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            align-items: center;
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid var(--border);
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.3rem;
        }

        .no-patients {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .no-patients i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }

        .clinic-footer {
            margin-top: 1.5rem;
            padding: 1rem 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
            border-top: 1px solid var(--border);
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
            .search-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-bar .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .dashboard-title {
                font-size: 1.2rem;
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
                        <i class="fas fa-search"></i>
                        Search Patients
                    </h1>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Search Patients</li>
                    </ol>
                </nav>

                <!-- Search Bar -->
                <div class="search-bar fade-in">
                    <form method="get" class="d-flex flex-grow-1 gap-2 align-items-center">
                        <input type="text" class="form-control" id="searchInput" name="search" placeholder="Enter name (last, first, or middle)" value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit" class="btn btn-primary" title="Search patients">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='search-patient.php'" title="Clear search">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </form>
                </div>

                <!-- Patient List -->
                <div class="card fade-in">
                    <div class="card-body">
                        <?php if (empty($patients)): ?>
                            <div class="no-patients fade-in">
                                <i class="fas fa-users-slash"></i>
                                <p>No patients found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="patientsTable" class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Last Name</th>
                                            <th>First Name</th>
                                            <th>Middle Name</th>
                                            <th>Gender</th>
                                            <th>Category</th>
                                            <th>Grade/Year</th>
                                            <th>Program/Section</th>
                                            <th>Guardian Contact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $patient): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($patient['id']) ?></td>
                                                <td><?= htmlspecialchars($patient['last_name']) ?></td>
                                                <td><?= htmlspecialchars($patient['first_name']) ?></td>
                                                <td><?= htmlspecialchars($patient['middle_name'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($patient['gender']) ?></td>
                                                <td><?= htmlspecialchars($patient['category']) ?></td>
                                                <td><?= htmlspecialchars($patient['grade_year'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($patient['program_section'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($patient['guardian_contact'] ?: 'N/A') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

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
            // Initialize DataTable
            $('#patientsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[1, 'asc']],
                language: { search: "", searchPlaceholder: "Search within results..." }
            });
        });
    </script>
</body>
</html>