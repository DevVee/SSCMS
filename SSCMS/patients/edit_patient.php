```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if patient_id is provided
$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    $_SESSION['error_message'] = 'Invalid patient ID';
    header('Location: manage-patients.php');
    exit;
}

// Fetch patient data
try {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        $_SESSION['error_message'] = 'Patient not found';
        header('Location: manage-patients.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: manage-patients.php');
    exit;
}

// Fetch grade years and program sections
$gradeYears = $conn->query("SELECT * FROM grade_years ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
$programSections = $conn->query("SELECT * FROM program_sections ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
$academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College'];
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING) ?: $patient['category'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - Clinic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-size: 0.9rem; }
        .sidebar { width: 200px; background: #f8f9fa; transition: width 0.3s; }
        .sidebar .nav-link { color: #333; font-size: 0.85rem; padding: 8px 15px; }
        .sidebar .nav-link:hover { background: #e9ecef; }
        .sidebar .nav-link.active { background: #0284c7; color: white; }
        .profile-dropdown .dropdown-menu { font-size: 0.85rem; }
        .main-content { margin-left: 200px; padding: 20px; }
        .btn-primary { background-color: #0284c7; border-color: #0284c7; }
        .btn-primary:hover { background-color: #026aa7; border-color: #026aa7; }
        .card { border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-control, .form-select { font-size: 0.85rem; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .nav-link span { display: none; }
            .main-content { margin-left: 60px; }
        }
        .dark-mode { background: #343a40; color: #f8f9fa; }
        .dark-mode .sidebar { background: #212529; }
        .dark-mode .nav-link { color: #f8f9fa; }
        .dark-mode .nav-link:hover { background: #495057; }
        .dark-mode .nav-link.active { background: #0284c7; }
        .dark-mode .card { background: #495057; color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="main-content">
        <h1 class="mb-4">Edit Patient</h1>

        <!-- Preview Card -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Patient Preview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ?: '')) ?></p>
                        <p><strong>Gender:</strong> <?= htmlspecialchars($patient['gender'] ?: 'N/A') ?></p>
                        <p><strong>Category:</strong> <?= htmlspecialchars($patient['category'] ?: 'N/A') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Grade/Year:</strong> <?= htmlspecialchars($patient['grade_year'] ?: 'N/A') ?></p>
                        <p><strong>Program/Section:</strong> <?= htmlspecialchars($patient['program_section'] ?: 'N/A') ?></p>
                        <p><strong>Guardian Contact:</strong> <?= htmlspecialchars($patient['guardian_contact'] ?: 'N/A') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Update Patient Details</h5>
            </div>
            <div class="card-body">
                <form id="editPatientForm" action="../shared/manage-patients-process.php" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="patient_id" id="patient_id" value="<?= $patient['id'] ?>">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($patient['last_name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($patient['first_name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= htmlspecialchars($patient['middle_name'] ?: '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?= $patient['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= $patient['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach (['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'] as $cat): ?>
                                    <option value="<?= $cat ?>" <?= $patient['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="guardian_contact" class="form-label">Guardian Contact</label>
                            <input type="text" class="form-control" id="guardian_contact" name="guardian_contact" value="<?= htmlspecialchars($patient['guardian_contact'] ?: '') ?>">
                        </div>
                    </div>

                    <div id="academicFields" style="display: <?= in_array($patient['category'], $academicCategories) && $patient['category'] !== 'Alumni' ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="grade_year" class="form-label">Grade/Year</label>
                                <select class="form-select" id="grade_year" name="grade_year">
                                    <option value="">Select Grade/Year</option>
                                </select>
                                <div id="grade_year_error" class="text-danger small d-none">No grade/year available for this category.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="program_section" class="form-label">Program/Section</label>
                                <select class="form-select" id="program_section" name="program_section">
                                    <option value="">Select Program/Section</option>
                                </select>
                                <div id="program_section_error" class="text-danger small d-none">No program/section available for this category.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <a href="manage-patients.php<?= $category ? '?category=' . urlencode($category) : '' ?>" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const gradeYears = <?= json_encode($gradeYears) ?>;
        const programSections = <?= json_encode($programSections) ?>;
        const academicCategories = <?= json_encode($academicCategories) ?>;

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

        function toggleFields(category) {
            if (academicCategories.includes(category) && category !== 'Alumni') {
                $('#academicFields').show();
                $('#grade_year').prop('required', false);
                $('#program_section').prop('required', false);
                populateDropdowns(category, '#grade_year', '#program_section', '#grade_year_error', '#program_section_error', '<?= addslashes($patient['grade_year'] ?: '') ?>', '<?= addslashes($patient['program_section'] ?: '') ?>');
            } else {
                $('#academicFields').hide();
                $('#grade_year').prop('required', false);
                $('#program_section').prop('required', false);
                $('#grade_year').empty().append('<option value="">Select Grade/Year</option>');
                $('#program_section').empty().append('<option value="">Select Program/Section</option>');
                $('#grade_year_error').addClass('d-none');
                $('#program_section_error').addClass('d-none');
            }
        }

        $(document).ready(function() {
            // Initialize category fields
            toggleFields('<?= addslashes($patient['category']) ?>');

            // Category change handler
            $('#category').on('change', function() {
                toggleFields($(this).val());
            });

            // Form submission
            $('#editPatientForm').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $submitButton = $form.find('[type=submit]');
                const $spinner = $submitButton.find('.spinner-border');

                $submitButton.prop('disabled', true);
                $spinner.removeClass('d-none');

                console.log('Submitting form with data:', $form.serialize());

                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        console.log('Success response:', response);
                        if (response.success) {
                            window.location.href = 'manage-patients.php<?= $category ? '?category=' . urlencode($category) : '' ?>';
                        } else {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $submitButton.prop('disabled', false);
                        $spinner.addClass('d-none');
                        console.error('AJAX error:', { status, error, response: xhr.responseText });
                        alert('Error: ' + (xhr.responseJSON?.message || `Server error (Status: ${xhr.status})`));
                    }
                });
            });

            // Dark mode toggle
            $('#darkModeToggle').on('click', function() {
                $('body').toggleClass('dark-mode');
                const isDark = $('body').hasClass('dark-mode');
                $(this).html(`<i class="fas fa-${isDark ? 'sun' : 'moon'}"></i>`);
                localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
            });

            // Initialize dark mode
            if (localStorage.getItem('darkMode') === 'enabled') {
                $('body').addClass('dark-mode');
                $('#darkModeToggle').html('<i class="fas fa-sun"></i>');
            }
        });
    </script>
</body>
</html>
```