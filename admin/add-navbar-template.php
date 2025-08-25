<!-- TEMPLATE: Add this navbar code to each admin page after the sidebar and before the main content -->

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="index.php">CITSA Admin</a>
        
        <!-- Sidebar Toggle Button -->
        <button class="btn btn-outline-primary me-2" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>

        <!-- Right Side (Admin Info / Profile) -->
        <ul class="nav navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown">
                    <img src="../profile/<?php echo $admin['profile_image'] ?? 'default-avatar.png'; ?>" 
                         class="rounded-circle me-2" width="32" height="32" alt="Admin">
                    <span><?php echo htmlspecialchars($admin['name'] ?? $admin['username'] ?? $admin['email'] ?? 'Admin'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<!-- TEMPLATE: Add this JavaScript code before the closing </script> tag in each admin page -->

// Sidebar Toggle Functionality
const sidebar = document.querySelector('.sidebar');
const mainContent = document.querySelector('.main-content');
const topNavbar = document.querySelector('.navbar');
const sidebarToggle = document.getElementById('sidebarToggle');

sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    if (sidebar.classList.contains('active')) {
        // Sidebar hidden
        sidebar.style.transform = 'translateX(-100%)';
        mainContent.style.marginLeft = '0';
        topNavbar.style.marginLeft = '0';
    } else {
        // Sidebar shown
        sidebar.style.transform = 'translateX(0)';
        mainContent.style.marginLeft = '280px';
        topNavbar.style.marginLeft = '280px';
    }
});

<!-- TEMPLATE: Replace the CSS link in the <head> section of each admin page -->

<!-- OLD: -->
<!-- <link href="includes/navbar.css" rel="stylesheet"> -->

<!-- NEW: -->
<!-- <link href="includes/admin-styles.css" rel="stylesheet"> -->

<!-- TEMPLATE: Remove all inline sidebar and navbar CSS from each admin page since it's now in admin-styles.css -->
