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

// Updated categories
$categories = [
    ['name' => 'Pre School', 'icon' => 'fa-child', 'description' => 'Pre-school students', 'color' => '#0284c7'],
    ['name' => 'Elementary', 'icon' => 'fa-school', 'description' => 'Elementary students', 'color' => '#059669'],
    ['name' => 'JHS', 'icon' => 'fa-book', 'description' => 'Junior High School students', 'color' => '#d97706'],
    ['name' => 'SHS', 'icon' => 'fa-graduation-cap', 'description' => 'Senior High School students', 'color' => '#dc2626'],
    ['name' => 'College', 'icon' => 'fa-university', 'description' => 'College students', 'color' => '#7c3aed'],
    ['name' => 'Alumni', 'icon' => 'fa-user-graduate', 'description' => 'Graduated students', 'color' => '#4b5563']
];

// Academic categories
$academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College'];

// Fetch grade/year and program/section
$gradeYearsStmt = $conn->query("SELECT name, category FROM grade_years ORDER BY category, name");
$gradeYears = $gradeYearsStmt->fetchAll(PDO::FETCH_ASSOC);

$programSectionsStmt = $conn->query("SELECT name, category FROM program_sections ORDER BY category, name");
$programSections = $programSectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch patients
$patients = [];
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$selectedCategory = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING) ?? '';
$selectedGradeYear = filter_input(INPUT_GET, 'grade_year', FILTER_SANITIZE_STRING) ?? '';
$selectedProgramSection = filter_input(INPUT_GET, 'program_section', FILTER_SANITIZE_STRING) ?? '';

$query = "SELECT * FROM patients WHERE 1=1";
$params = [];

if ($searchTerm) {
    $query .= " AND (last_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ?)";
    $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
}

if ($selectedCategory) {
    $query .= " AND category = ?";
    $params[] = str_replace('-', ' ', ucwords($selectedCategory));
}

if ($selectedGradeYear) {
    $query .= " AND grade_year = ?";
    $params[] = $selectedGradeYear;
}

if ($selectedProgramSection) {
    $query .= " AND program_section = ?";
    $params[] = $selectedProgramSection;
}

$query .= " ORDER BY last_name, first_name, middle_name";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category details
$categoryDetails = null;
if ($selectedCategory) {
    foreach ($categories as $cat) {
        if (strtolower(str_replace(' ', '-', $cat['name'])) === strtolower($selectedCategory)) {
            $categoryDetails = $cat;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Manage Patients">
    <meta name="author" content="ICCB">
    <title>Manage Patients - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/manage-patients.css">
    <style>
       
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
                        <i class="fas fa-users"></i>
                        Manage Patients
                    </h1>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Manage Patients</li>
                        <?php if ($selectedCategory): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars(str_replace('-', ' ', ucwords($selectedCategory))) ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>

                <!-- Toast Container -->
                <div class="toast-container position-fixed top-0 end-0 p-3">
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

                <?php if ($selectedCategory): ?>
                    <!-- Category Info -->
                    <div class="category-info fade-in">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-1 fw-semibold"><?= htmlspecialchars(str_replace('-', ' ', ucwords($selectedCategory))) ?></h5>
                                <p class="mb-0 text-muted"><?= htmlspecialchars($categoryDetails['description']) ?></p>
                            </div>
                            <a href="manage-patients.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>

                    <!-- Toolbar -->
                    <div class="toolbar fade-in">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal" title="Add a new patient">
                            <i class="fas fa-plus"></i> Add Patient
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal" title="Filter patients">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button class="btn btn-success" id="editButton" disabled title="Edit selected patient">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-warning" id="moveButton" disabled data-bs-toggle="modal" data-bs-target="#movePatientModal" title="Move or promote selected patients">
                            <i class="fas fa-arrows-alt"></i> Move/Promote
                        </button>
                        <button class="btn btn-danger" id="deleteButton" disabled data-bs-toggle="modal" data-bs-target="#deletePatientModal" title="Delete selected patients">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <span class="ms-auto align-self-center text-muted" id="selectionCount">0 selected</span>
                    </div>

                    <!-- Patient List -->
                    <div class="card fade-in">
                        <div class="card-body">
                            <?php if (empty($patients)): ?>
                                <div class="no-patients fade-in">
                                    <i class="fas fa-users-slash"></i>
                                    <p>No patients in this category</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="patientsTable" class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll"></th>
                                                <th>Last Name</th>
                                                <th>First Name</th>
                                                <th>Middle Name</th>
                                                <th>Gender</th>
                                                <th>Grade/Year</th>
                                                <th>Program/Section</th>
                                                <th>Guardian Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patients as $patient): ?>
                                                <tr>
                                                    <td><input type="checkbox" class="patient-checkbox" value="<?= $patient['id'] ?>" data-patient='<?= json_encode($patient) ?>'></td>
                                                    <td><?= htmlspecialchars($patient['last_name']) ?></td>
                                                    <td><?= htmlspecialchars($patient['first_name']) ?></td>
                                                    <td><?= htmlspecialchars($patient['middle_name'] ?: 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($patient['gender']) ?></td>
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
                <?php else: ?>
                    <!-- Category View Toggle -->
                    <div class="toolbar fade-in">
                        <button class="btn btn-outline-primary active" data-view="card" title="Card View">
                            <i class="fas fa-th-large"></i> Cards
                        </button>
                        <button class="btn btn-outline-primary" data-view="list" title="List View">
                            <i class="fas fa-list"></i> List
                        </button>
                    </div>

                    <!-- Category Cards -->
                    <div class="category-view-card active">
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3 mb-3">
                            <?php foreach ($categories as $index => $category): ?>
                                <div class="col">
                                    <a href="?category=<?= strtolower(str_replace(' ', '-', $category['name'])) ?>" class="text-decoration-none">
                                        <div class="card category-card fade-in-delay-1" style="border-left-color: <?= $category['color'] ?>;">
                                            <i class="fas <?= htmlspecialchars($category['icon']) ?> icon"></i>
                                            <h5 class="card-title"><?= htmlspecialchars($category['name']) ?></h5>
                                            <p class="card-text"><?= htmlspecialchars($category['description']) ?></p>
                                            <span class="badge" style="background-color: <?= $category['color'] ?>;">
                                                <?php
                                                $stmt = $conn->prepare("SELECT COUNT(*) FROM patients WHERE category = ?");
                                                $stmt->execute([$category['name']]);
                                                $count = $stmt->fetchColumn();
                                                echo $count . ' patient' . ($count !== 1 ? 's' : '');
                                                ?>
                                            </span>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Category List -->
                    <div class="category-view-list">
                        <div class="card fade-in">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Patients</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
                                                    <td>
                                                        <a href="?category=<?= strtolower(str_replace(' ', '-', $category['name'])) ?>" class="text-primary">
                                                            <?= htmlspecialchars($category['name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($category['description']) ?></td>
                                                    <td>
                                                        <?php
                                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM patients WHERE category = ?");
                                                        $stmt->execute([$category['name']]);
                                                        $count = $stmt->fetchColumn();
                                                        echo $count . ' patient' . ($count !== 1 ? 's' : '');
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add Patient Modal -->
                <div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-semibold" id="addPatientModalLabel">Add New Patient</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addPatientForm" method="post" action="../shared/manage-patients-process.php">
                                    <input type="hidden" name="action" value="add">
                                    <div class="mb-2">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="middle_name" class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name">
                                    </div>
                                    <div class="mb-2">
                                        <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $selectedCategory && str_replace('-', ' ', ucwords($selectedCategory)) === $cat['name'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div id="academicFields">
                                        <div class="mb-2">
                                            <label for="grade_year" class="form-label">Grade/Year</label>
                                            <select class="form-select" id="grade_year" name="grade_year"></select>
                                            <div id="grade_year_error" class="text-danger small mt-1 d-none">No grade/year options available for this category.</div>
                                        </div>
                                        <div class="mb-2">
                                            <label for="program_section" class="form-label">Program/Section</label>
                                            <select class="form-select" id="program_section" name="program_section"></select>
                                            <div id="program_section_error" class="text-danger small mt-1 d-none">No program/section options available for this category.</div>
                                        </div>
                                        <div class="mb-2">
                                            <label for="guardian_contact" class="form-label">Guardian Contact</label>
                                            <input type="text" class="form-control" id="guardian_contact" name="guardian_contact">
                                            <div id="guardian_contact_error" class="text-danger small mt-1 d-none">Guardian contact is required.</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save"></i> Save
                                            <span id="addSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Move Patients Modal -->
                <div class="modal fade" id="movePatientModal" tabindex="-1" aria-labelledby="movePatientModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-semibold" id="movePatientModalLabel">Move/Promote Patients</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="movePatientForm" method="post" action="../shared/manage-patients-process.php">
                                    <input type="hidden" name="action" value="move">
                                    <input type="hidden" id="move_patient_ids" name="patient_ids">
                                    <div class="mb-2">
                                        <label for="new_category" class="form-label">New Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="new_category" name="new_category" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>">
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div id="move_academicFields">
                                        <div class="mb-2">
                                            <label for="new_grade_year" class="form-label">New Grade/Year</label>
                                            <select class="form-select" id="new_grade_year" name="new_grade_year"></select>
                                            <div id="move_grade_year_error" class="text-danger small mt-1 d-none">No grade/year options available for this category.</div>
                                        </div>
                                        <div class="mb-2">
                                            <label for="new_program_section" class="form-label">New Program/Section</label>
                                            <select class="form-select" id="new_program_section" name="new_program_section"></select>
                                            <div id="move_program_section_error" class="text-danger small mt-1 d-none">No program/section options available for this category.</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-arrows-alt"></i> Move/Promote
                                            <span id="moveSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delete Patients Modal -->
                <div class="modal fade" id="deletePatientModal" tabindex="-1" aria-labelledby="deletePatientModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-semibold" id="deletePatientModalLabel">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="deletePatientForm" method="post" action="../shared/manage-patients-process.php">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" id="delete_patient_ids" name="patient_ids">
                                    <p>Are you sure you want to delete the selected patient(s)? This action cannot be undone.</p>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                            <span id="deleteSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Modal -->
                <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-semibold" id="filterModalLabel">Filter Patients</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="filterForm">
                                    <div class="mb-2">
                                        <label for="filterName" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="filterName" placeholder="Enter name" value="<?= htmlspecialchars($searchTerm) ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label for="filterGradeYear" class="form-label">Grade/Year</label>
                                        <select class="form-select" id="filterGradeYear">
                                            <option value="">All Grades/Years</option>
                                            <?php foreach ($gradeYears as $gy): ?>
                                                <?php if ($gy['category'] === str_replace('-', ' ', ucwords($selectedCategory))): ?>
                                                    <option value="<?= htmlspecialchars($gy['name']) ?>" <?= $selectedGradeYear === $gy['name'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($gy['name']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label for="filterProgramSection" class="form-label">Program/Section</label>
                                        <select class="form-select" id="filterProgramSection">
                                            <option value="">All Programs/Sections</option>
                                            <?php foreach ($programSections as $ps): ?>
                                                <?php if ($ps['category'] === str_replace('-', ' ', ucwords($selectedCategory))): ?>
                                                    <option value="<?= htmlspecialchars($ps['name']) ?>" <?= $selectedProgramSection === $ps['name'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($ps['name']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <input type="hidden" id="filterCategory" value="<?= htmlspecialchars($selectedCategory) ?>">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                            <i class="fas fa-filter"></i> Apply
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetFilterForm()">Reset</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Modal -->
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-semibold" id="confirmModalLabel"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="confirmModalBody"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary btn-sm" id="confirmActionBtn">Confirm</button>
                            </div>
                        </div>
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
        const gradeYears = <?php echo json_encode($gradeYears); ?>;
        const programSections = <?php echo json_encode($programSections); ?>;
        const academicCategories = <?php echo json_encode($academicCategories); ?>;

        function populateDropdowns(category, gradeYearSelect, programSectionSelect, gradeYearError, programSectionError, selectedGradeYear = '', selectedProgramSection = '') {
            const gradeYearOptions = gradeYears.filter(gy => gy.category === category);
            const programSectionOptions = programSections.filter(ps => ps.category === category);

            $(gradeYearSelect).empty().append('<option value="">Select Grade/Year</option>');
            $(programSectionSelect).empty().append('<option value="">Select Program/Section</option>');

            if (gradeYearOptions.length === 0) {
                $(gradeYearError).removeClass('d-none');
                $(gradeYearSelect).prop('disabled', true);
            } else {
                $(gradeYearError).addClass('d-none');
                $(gradeYearSelect).prop('disabled', false);
                gradeYearOptions.forEach(gy => {
                    $(gradeYearSelect).append(`<option value="${gy.name}" ${gy.name === selectedGradeYear ? 'selected' : ''}>${gy.name}</option>`);
                });
            }

            if (programSectionOptions.length === 0) {
                $(programSectionError).removeClass('d-none');
                $(programSectionSelect).prop('disabled', true);
            } else {
                $(programSectionError).addClass('d-none');
                $(programSectionSelect).prop('disabled', false);
                programSectionOptions.forEach(ps => {
                    $(programSectionSelect).append(`<option value="${ps.name}" ${ps.name === selectedProgramSection ? 'selected' : ''}>${ps.name}</option>`);
                });
            }
        }

        function toggleFields(category, academicFields, gradeYearSelect, programSectionSelect, guardianContact, gradeYearError, programSectionError, guardianContactError) {
            if (academicCategories.includes(category) && category !== 'Alumni') {
                $(academicFields).show();
                $(gradeYearSelect).prop('required', false);
                $(programSectionSelect).prop('required', false);
                if (guardianContact) $(guardianContact).prop('required', false);
                populateDropdowns(category, gradeYearSelect, programSectionSelect, gradeYearError, programSectionError);
            } else {
                $(academicFields).hide();
                $(gradeYearSelect).prop('required', false);
                $(programSectionSelect).prop('required', false);
                if (guardianContact) $(guardianContact).prop('required', false);
                $(gradeYearSelect).empty().append('<option value="">Select Grade/Year</option>');
                $(programSectionSelect).empty().append('<option value="">Select Program/Section</option>');
                $(gradeYearError).addClass('d-none');
                $(programSectionError).addClass('d-none');
                if (guardianContactError) $(guardianContactError).addClass('d-none');
            }
        }

        function submitForm(formId, title, message) {
            const $form = $(`#${formId}`);
            const $submitButton = $form.find('[type=submit]');
            const $spinner = $submitButton.find('.spinner-border');

            $('#confirmModalLabel').text(title);
            $('#confirmModalBody').text(message);
            const confirmModal = new bootstrap.Modal('#confirmModal');
            confirmModal.show();

            $('#confirmActionBtn').off('click').on('click', function() {
                $submitButton.prop('disabled', true);
                $spinner.removeClass('d-none');

                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        $submitButton.prop('disabled', false);
                        $spinner.addClass('d-none');
                        alert('Error: ' + (xhr.responseJSON?.message || 'An error occurred'));
                    }
                });

                confirmModal.hide();
            });
        }

        $(document).ready(function() {
            // Initialize DataTable
            $('#patientsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: 0 }],
                language: { search: "", searchPlaceholder: "Search patients..." }
            });

            // Category View Toggle
            $('[data-view]').on('click', function() {
                $('[data-view]').removeClass('active');
                $(this).addClass('active');
                $('.category-view-card, .category-view-list').removeClass('active');
                $(`.category-view-${$(this).data('view')}`).addClass('active');
            });

            // Add Patient Modal
            $('#addPatientModal').on('show.bs.modal', function() {
                const selectedCategory = '<?php echo str_replace('-', ' ', ucwords($selectedCategory)); ?>';
                if (selectedCategory) {
                    $('#category').val(selectedCategory);
                    toggleFields(selectedCategory, '#academicFields', '#grade_year', '#program_section', '#guardian_contact', '#grade_year_error', '#program_section_error', '#guardian_contact_error');
                } else {
                    $('#category').val('');
                    toggleFields('', '#academicFields', '#grade_year', '#program_section', '#guardian_contact', '#grade_year_error', '#program_section_error', '#guardian_contact_error');
                }
            });

            $('#category').on('change', function() {
                toggleFields($(this).val(), '#academicFields', '#grade_year', '#program_section', '#guardian_contact', '#grade_year_error', '#program_section_error', '#guardian_contact_error');
            });

            $('#addPatientForm').on('submit', function(e) {
                e.preventDefault();
                submitForm('addPatientForm', 'Add Patient', 'Are you sure you want to add this patient?');
            });

            // Edit Button
            $('#editButton').on('click', function() {
                const selected = $('.patient-checkbox:checked');
                if (selected.length === 1) {
                    const patientId = selected.val();
                    const category = '<?php echo addslashes(str_replace('-', ' ', ucwords($selectedCategory))); ?>';
                    window.location.href = `edit_patient.php?id=${patientId}${category ? '&category=' + encodeURIComponent(category) : ''}`;
                }
            });

            // Move Patients Modal
            $('#moveButton').on('click', function() {
                const selectedIds = $('.patient-checkbox:checked').map(function() { return $(this).val(); }).get();
                $('#move_patient_ids').val(selectedIds.join(','));
                $('#new_category').val('');
                toggleFields('', '#move_academicFields', '#new_grade_year', '#new_program_section', null, '#move_grade_year_error', '#move_program_section_error', null);
            });

            $('#new_category').on('change', function() {
                toggleFields($(this).val(), '#move_academicFields', '#new_grade_year', '#new_program_section', null, '#move_grade_year_error', '#move_program_section_error', null);
            });

            $('#movePatientForm').on('submit', function(e) {
                e.preventDefault();
                const selectedCount = $('.patient-checkbox:checked').length;
                submitForm('movePatientForm', 'Move/Promote Patients', `Are you sure you want to move ${selectedCount} patient${selectedCount > 1 ? 's' : ''}?`);
            });

            // Delete Patients Modal
            $('#deleteButton').on('click', function() {
                const selectedIds = $('.patient-checkbox:checked').map(function() { return $(this).val(); }).get();
                $('#delete_patient_ids').val(selectedIds.join(','));
            });

            $('#deletePatientForm').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $submitButton = $form.find('[type=submit]');
                const $spinner = $submitButton.find('.spinner-border');
                const selectedCount = $('.patient-checkbox:checked').length;

                $submitButton.prop('disabled', true);
                $spinner.removeClass('d-none');

                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        $submitButton.prop('disabled', false);
                        $spinner.addClass('fit d-none');
                        alert('Error: ' + (xhr.responseJSON?.message || 'An error occurred'));
                    }
                });
            });

            // Filter Form
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams();
                if ($('#filterName').val()) params.append('search', $('#filterName').val());
                if ($('#filterCategory').val()) params.append('category', $('#filterCategory').val());
                if ($('#filterGradeYear').val()) params.append('grade_year', $('#filterGradeYear').val());
                if ($('#filterProgramSection').val()) params.append('program_section', $('#filterProgramSection').val());
                window.location.href = `manage-patients.php?${params.toString()}`;
            });

            function resetFilterForm() {
                $('#filterForm')[0].reset();
                $('#filterGradeYear, #filterProgramSection').val('');
                window.location.href = 'manage-patients.php' + ($('#filterCategory').val() ? `?category=${$('#filterCategory').val()}` : '');
            }

            // Checkbox Selection
            $('#selectAll').on('change', function() {
                $('.patient-checkbox').prop('checked', this.checked);
                updateSelection();
            });

            $('.patient-checkbox').on('change', updateSelection);

            function updateSelection() {
                const selectedCount = $('.patient-checkbox:checked').length;
                $('#selectionCount').text(`${selectedCount} selected`);
                $('#editButton').prop('disabled', selectedCount !== 1);
                $('#moveButton, #deleteButton').prop('disabled', selectedCount === 0);
            }

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>
