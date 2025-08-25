<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top" style="margin-left:280px; transition: margin-left 0.3s;">
    <div class="container-fluid">
        <!-- Sidebar Toggle Button -->
        <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="index.php">CITSA Admin</a>

        <!-- Right Side (Admin Info / Profile) -->
        <ul class="nav navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown">
                    <img src="../profile/<?php echo $admin['profile_image'] ?? 'default-avatar.png'; ?>" 
                         class="rounded-circle me-2" width="32" height="32" alt="Admin">
                    <span><?php echo htmlspecialchars($admin['first_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
