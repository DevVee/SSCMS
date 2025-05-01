<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate user session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("Unauthorized access attempt to navigations.php: " . (isset($_SESSION['user_id']) ? "user_id: {$_SESSION['user_id']}" : "no session"));
    header('Location: /SSCMS/login.php');
    exit;
}

// Fallback user data
$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin';
$admin_category = isset($_SESSION['admin_category']) ? htmlspecialchars($_SESSION['admin_category']) : 'Staff';
$profile_picture = isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? htmlspecialchars($_SESSION['profile_picture']) : null;

// Sanitize current file for active link
$current_file = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
?>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <!-- Sidebar Toggle Button -->
        <button class="navbar-toggler" type="button" id="sidebarToggler" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand" href="/SSCMS/dashboard.php">
            <img src="/SSCMS/assets/img/ICCLOGO.png" alt="SSCMS Logo" class="brand-icon" style="height: 24px;" onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
            <span>Smart School Clinic Management System</span>
        </a>

        <!-- Spacer -->
        <div class="flex-grow-1"></div>

     

        <!-- Profile Dropdown -->
        <div class="profile-dropdown">
            <a href="#" class="dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="profile-avatar">
                    <?php if ($profile_picture): ?>
                        <img src="<?= $profile_picture ?>" alt="Profile" class="profile-image" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-user-md default-avatar"></i>
                    <?php endif; ?>
                </div>
                <span><?= $user_name ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                <li><a class="dropdown-item" href="/SSCMS/settings.php"><i class="fas fa-user-circle"></i> Manage Profile</a></li>
                <li><a class="dropdown-item" href="/SSCMS/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="/SSCMS/assets/img/ICCLOGO.png" alt="SSCMS Logo" class="brand-icon" style="height: 24px;" onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg=='">
        <div>
            <div class="fw-bold">SSCMS</div>
            <small class="text-muted">SY 2024-2025</small>
        </div>
    </div>

    <div class="sidebar-header">
        <div class="profile-image-container">
            <?php if ($profile_picture): ?>
                <img src="<?= $profile_picture ?>" alt="Profile" class="profile-image" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <i class="fas fa-user-md default-avatar"></i>
            <?php endif; ?>
        </div>
        <p class="mb-0 fw-semibold"><?= $user_name ?></p>
        <small class="text-muted"><?= $admin_category ?></small>
    </div>

    <!-- Rest of the sidebar (nav links) remains unchanged -->
    <nav class="mt-2">
        <!-- Existing nav links from your provided navigations.php -->
        <div class="nav-section">
            <span>Home</span>
        </div>
        <a class="nav-link <?= $current_file === 'dashboard.php' ? 'active' : '' ?>" href="/SSCMS/dashboard.php">
            <div class="nav-icon">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <span>Dashboard</span>
        </a>

        <div class="nav-section">
            <span>Patients</span>
        </div>
        <a class="nav-link <?= $current_file === 'manage-patients.php' ? 'active' : '' ?>" href="/SSCMS/patients/manage-patients.php">
            <div class="nav-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <span>Manage Patients</span>
        </a>
        <a class="nav-link <?= $current_file === 'search-patient.php' ? 'active' : '' ?>" href="/SSCMS/patients/search-patient.php">
            <div class="nav-icon">
                <i class="fas fa-search"></i>
            </div>
            <span>Search Patients</span>
        </a>

        <div class="nav-section">
            <span>Appointments</span>
        </div>
        <a class="nav-link <?= $current_file === 'new-appointment.php' ? 'active' : '' ?>" href="/SSCMS/appointments/new-appointment.php">
            <div class="nav-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <span>New Appointment</span>
        </a>
        <a class="nav-link <?= $current_file === 'appointment-list.php' ? 'active' : '' ?>" href="/SSCMS/appointments/appointment-list.php">
            <div class="nav-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <span>Appointment List</span>
        </a>

        <div class="nav-section">
            <span>Reports</span>
        </div>
        <a class="nav-link <?= $current_file === 'daily-reports.php' ? 'active' : '' ?>" href="/SSCMS/reports/daily-reports.php">
            <div class="nav-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <span>Daily Reports</span>
        </a>
        <a class="nav-link <?= $current_file === 'monthly-report.php' ? 'active' : '' ?>" href="/SSCMS/reports/monthly-report.php">
            <div class="nav-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <span>Monthly Report</span>
        </a>

        <div class="nav-section">
            <span>Inventory</span>
        </div>
        <a class="nav-link <?= $current_file === 'inventory.php' ? 'active' : '' ?>" href="/SSCMS/inventory/inventory.php">
            <div class="nav-icon">
                <i class="fas fa-pills"></i>
            </div>
            <span>Medicine Inventory</span>
        </a>

        <div class="nav-section">
            <span>System</span>
        </div>
        <a class="nav-link <?= $current_file === 'settings.php' ? 'active' : '' ?>" href="/SSCMS/settings.php">
            <div class="nav-icon">
                <i class="fas fa-cog"></i>
            </div>
            <span>Settings</span>
        </a>
        <a class="nav-link" href="/SSCMS/logout.php">
            <div class="nav-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <span>Logout</span>
        </a>
    </nav>
</div>

<!-- Sidebar Overlay and Styles/Scripts remain unchanged -->
<!-- Include the existing styles and scripts from your navigations.php -->
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

    [data-theme="dark"] {
        --primary: #4dabf7;
        --primary-dark: #2b6cb0;
        --secondary: #a0aec0;
        --secondary-dark: #718096;
        --background: #1a202c;
        --card-bg: #2d3748;
        --text-primary: #e2e8f0;
        --text-secondary: #a0aec0;
        --border: #4a5568;
        --success: #4fd1c5;
        --success-dark: #2c7a7b;
        --danger: #f56565;
        --danger-dark: #c53030;
        --warning: #f6ad55;
        --warning-dark: #744210;
        --purple: #b794f4;
        --purple-dark: #553c9a;
        --bscs-maroon: #9b2c2c;
    }

    /* Existing styles from your navigations.php */
    html {
        transition: all var(--transition-speed) ease;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background-color: var(--background);
        color: var(--text-primary);
        margin: 0;
        padding: 0;
    }

    .navbar {
        background: var(--card-bg);
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        padding: 0.5rem 1rem;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        height: var(--header-height);
    }

    .navbar-brand {
        font-weight: 700;
        color: var(--primary);
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: color var(--transition-speed) ease;
    }

    .navbar-brand:hover {
        color: var(--primary-dark);
    }

    .brand-icon {
        height: 24px;
        object-fit: contain;
    }

    .navbar-toggler {
        border: none;
        padding: 0.4rem;
        border-radius: 0.25rem;
        color: var(--text-primary);
        background: #e0f2fe;
        transition: all var(--transition-speed) ease;
    }

    .navbar-toggler:hover {
        background: var(--primary);
        color: #fff;
    }

    .navbar-toggler:focus {
        box-shadow: none;
    }

    .btn-icon.dark-mode-toggle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e0f2fe;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
        transition: all var(--transition-speed) ease;
    }

    .btn-icon.dark-mode-toggle:hover {
        background: var(--primary-dark);
        color: #fff;
    }

    .profile-dropdown .dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-primary);
        font-weight: 500;
        padding: 0.4rem 0.75rem;
        border-radius: 0.375rem;
        text-decoration: none;
        transition: all var(--transition-speed) ease;
    }

    .profile-dropdown .dropdown-toggle:hover {
        background: #e0f2fe;
        color: var(--primary);
    }

    .profile-dropdown .dropdown-toggle::after {
        display: none;
    }

    .profile-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #e0f2fe;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 0.9rem;
    }

    .profile-dropdown .dropdown-menu {
        border-radius: 0.375rem;
        border: 0.5px solid var(--border);
        box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        padding: 0.4rem 0;
        min-width: 180px;
        animation: dropdown-animation 0.2s ease forwards;
        transform-origin: top right;
    }

    .dropdown-item {
        padding: 0.5rem 1rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all var(--transition-speed) ease;
        font-size: 0.8rem;
    }

    .dropdown-item:hover {
        background: #e0f2fe;
        color: var(--primary);
    }

    .dropdown-item i {
        width: 16px;
        text-align: center;
        color: var(--text-secondary);
    }

    .dropdown-item:hover i {
        color: var(--primary);
    }

    @keyframes dropdown-animation {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: var(--sidebar-width);
        padding-top: var(--header-height);
        overflow-y: auto;
        background: var(--card-bg);
        border-right: 0.5px solid var(--border);
        box-shadow: 1px 0 6px rgba(0,0,0,0.02);
        z-index: 1020;
        transition: left var(--transition-speed) ease;
    }

    .sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background-color: var(--border);
        border-radius: 20px;
    }

    .sidebar-brand {
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-bottom: 0.5px solid var(--border);
        background: var(--card-bg);
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: var(--header-height);
        z-index: 1025;
    }

    .sidebar-brand .fw-bold {
        font-size: 0.9rem;
        color: var(--primary);
    }

    .sidebar-brand .text-muted {
        font-size: 0.7rem;
    }

    .sidebar-header {
        padding: 1rem 1rem 0.5rem;
        text-align: center;
        border-bottom: 0.5px solid var(--border);
    }

    .profile-image-container {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e0f2fe;
        margin: 0 auto 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 1.2rem;
        border: 1px solid var(--primary);
    }

    .sidebar-header p {
        color: var(--text-primary);
        font-size: 0.9rem;
        margin-bottom: 0.2rem;
    }

    .sidebar-header small {
        color: var(--text-secondary);
        font-size: 0.75rem;
    }

    .nav-section {
        padding: 0.5rem 1rem 0.3rem;
        color: var(--text-secondary);
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .sidebar nav {
        padding-bottom: 1rem;
    }

    .sidebar .nav-link {
        display: flex;
        align-items: center;
        padding: 0.5rem 1rem;
        color: var(--text-primary);
        border-radius: 0.25rem;
        margin: 0.1rem 0.5rem;
        font-weight: 500;
        font-size: 0.75rem;
        min-height: 36px;
        transition: all var(--transition-speed) ease;
    }

    .sidebar .nav-link:hover {
        background: #e0f2fe;
        color: var(--primary);
    }

    .sidebar .nav-link.active {
        background: var(--primary);
        color: white;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }

    .nav-icon {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
        margin-right: 0.5rem;
        font-size: 0.8rem;
        transition: all var(--transition-speed) ease;
    }

    .nav-link:nth-child(2) .nav-icon { background: #e0f2fe; color: var(--primary); }
    .nav-link:nth-child(4) .nav-icon, .nav-link:nth-child(5) .nav-icon { background: #d1fae5; color: var(--success); }
    .nav-link:nth-child(7) .nav-icon, .nav-link:nth-child(8) .nav-icon { background: #fef3c7; color: var(--warning); }
    .nav-link:nth-child(10) .nav-icon, .nav-link:nth-child(11) .nav-icon { background: #e0e7ff; color: var(--purple); }
    .nav-link:nth-child(13) .nav-icon { background: #e0f2fe; color: var(--primary); }
    .nav-link:nth-child(15) .nav-icon { background: #fee2e2; color: var(--danger); }

    .sidebar .nav-link.active .nav-icon {
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .sidebar .nav-link:hover .nav-icon {
        transform: translateX(2px);
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.4);
        z-index: 1015;
        display: none;
        transition: all var(--transition-speed) ease;
    }

    .sidebar-overlay.show {
        display: block;
    }

    @media (max-width: 992px) {
        :root { --sidebar-width: var(--sidebar-collapsed-width); }

        .sidebar .nav-link span, .sidebar-header p, .sidebar-header small, .nav-section, .sidebar-brand div {
            display: none;
        }

        .sidebar-header {
            padding: 0.5rem 0;
        }

        .profile-image-container {
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
            border: 1px solid var(--primary);
        }

        .sidebar .nav-link {
            padding: 0.5rem 0;
            margin: 0.3rem auto;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            min-height: 36px;
        }

        .nav-icon {
            margin-right: 0;
            font-size: 0.9rem;
            width: 28px;
            height: 28px;
        }

        .sidebar-brand {
            width: var(--sidebar-collapsed-width);
            justify-content: center;
            padding: 0;
        }

        .sidebar-brand .brand-icon {
            margin: 0;
            height: 24px;
        }
    }

    @media (max-width: 768px) {
        :root { --sidebar-width: 200px; }

        .sidebar {
            left: calc(-1 * var(--sidebar-width));
        }

        .sidebar.show {
            left: 0;
        }

        .sidebar .nav-link span, .sidebar-header p, .sidebar-header small, .nav-section, .sidebar-brand div {
            display: block;
        }

        .sidebar .nav-link {
            width: auto;
            height: auto;
            padding: 0.5rem 1rem;
            justify-content: flex-start;
            border-radius: 0.25rem;
        }

        .nav-icon {
            margin-right: 0.5rem;
            border-radius: 0.25rem;
        }

        .sidebar-brand {
            width: var(--sidebar-width);
            justify-content: flex-start;
            padding: 0.75rem 1rem;
        }
    }

    @media (max-width: 576px) {
        .navbar-brand span {
            display: none;
        }

        .navbar-brand .brand-icon {
            height: 20px;
        }

        .profile-dropdown .dropdown-toggle span {
            display: none;
        }

        .profile-dropdown .dropdown-menu {
            min-width: 160px;
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .sidebar .nav-link {
        animation: fadeIn 0.3s ease forwards;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[SSCMS Nav] Initialized');

        // Select elements
        const sidebar = document.querySelector('#sidebar');
        const toggler = document.querySelector('#sidebarToggler');
        const overlay = document.querySelector('#sidebarOverlay');
        const darkModeToggle = document.querySelector('#darkModeToggle');
        const profileDropdown = document.querySelector('#profileDropdown');

        // Check for missing elements
        const missing = [];
        if (!sidebar) missing.push('sidebar');
        if (!toggler) missing.push('toggler');
        if (!overlay) missing.push('overlay');
        if (!darkModeToggle) missing.push('darkModeToggle');
        if (!profileDropdown) missing.push('profileDropdown');
        if (missing.length) {
            console.error('[SSCMS Nav] Missing elements:', missing.join(', '));
            return;
        }

        // Sidebar toggle
        toggler.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            console.log('[SSCMS Nav] Sidebar:', sidebar.classList.contains('show') ? 'Visible' : 'Hidden');
        });

        // Close sidebar on overlay click
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            console.log('[SSCMS Nav] Sidebar closed via overlay');
        });

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !toggler.contains(e.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                console.log('[SSCMS Nav] Sidebar closed on outside click');
            }
        });

   

        // Profile dropdown
        profileDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                dropdown.classList.toggle('show');
                console.log('[SSCMS Nav] Profile dropdown:', dropdown.classList.contains('show') ? 'Visible' : 'Hidden');
            }
        });

        // Close dropdown on outside click
        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.dropdown-menu.show');
            if (dropdown && !dropdown.contains(e.target) && !e.target.closest('#profileDropdown')) {
                dropdown.classList.remove('show');
                console.log('[SSCMS Nav] Dropdown closed');
            }
        });
    });
</script>