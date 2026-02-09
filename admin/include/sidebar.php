<?php
// Get current page filename for active state
$current_page = basename($_SERVER['PHP_SELF']);
$role_id = isset($_SESSION['role_id']) ? intval($_SESSION['role_id']) : 0;
// Roles: 1 = Admin, 2 = Technician, 3 = Owner

// Fetch Sidebar Counts
$sidebar_equipment_count = 0;
$sidebar_maintenance_count = 0;
$sidebar_breakdown_count = 0;

if (isset($conn)) {
    // Equipment Count
    $sql_eq = "SELECT COUNT(*) as count FROM equipment";
    $res_eq = mysqli_query($conn, $sql_eq);
    if ($res_eq)
        $sidebar_equipment_count = mysqli_fetch_assoc($res_eq)['count'];

    // Maintenance Count (Active)
    $sql_maint = "SELECT COUNT(*) as count FROM maintenance WHERE statuss NOT IN ('Completed', 'Done', 'Finished', 'Cancelled')";
    $res_maint = mysqli_query($conn, $sql_maint);
    if ($res_maint)
        $sidebar_maintenance_count = mysqli_fetch_assoc($res_maint)['count'];

    // Breakdown Count (Active)
    $sql_bd = "SELECT COUNT(*) as count FROM breakdown WHERE statuss NOT IN ('Fixed', 'Resolved', 'Closed')";
    $res_bd = mysqli_query($conn, $sql_bd);
    if ($res_bd)
        $sidebar_breakdown_count = mysqli_fetch_assoc($res_bd)['count'];
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img class="logo-img" src="../static/images/logo.JPG" alt="logo">
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php if ($role_id == 1 || $role_id == 3): // Admin & Owner ?>
                <li class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $current_page == 'equipment.php' ? 'active' : ''; ?>">
                    <a href="equipment.php" class="nav-link">
                        <i class="fas fa-laptop-medical"></i>
                        <span class="nav-text">Equipment</span>
                        <span class="badge"><?php echo $sidebar_equipment_count; ?></span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($role_id == 1 || $role_id == 2 || $role_id == 3): // All roles ?>
                <li class="nav-item <?php echo $current_page == 'maintenance.php' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="fas fa-wrench"></i>
                        <span class="nav-text">Maintenance</span>
                        <span class="badge warning"><?php echo $sidebar_maintenance_count; ?></span>
                    </a>
                </li>
                <li class="nav-item <?php echo ($current_page == 'schedules.php' || $current_page == 'schedules_technician.php') ? 'active' : ''; ?>">
                    <a href="<?php echo ($role_id == 2) ? 'schedules_technician.php' : 'schedules.php'; ?>" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Schedules</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $current_page == 'breakdowns.php' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="nav-text">Breakdowns</span>
                        <span class="badge danger"><?php echo $sidebar_breakdown_count; ?></span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($role_id == 1 || $role_id == 3): // Admin & Owner ?>
                <li class="nav-item <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>">
                    <a href="categories.php" class="nav-link">
                        <i class="fas fa-layer-group"></i>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>

            <?php endif; ?>

            <?php if ($role_id == 1 || $role_id == 2 || $role_id == 3): // All roles ?>
                <li class="nav-item <?php echo $current_page == 'equipment_location.php' ? 'active' : ''; ?>">
                    <a href="equipment_location.php" class="nav-link">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="nav-text">Locations</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($role_id == 1): // Admin only ?>
                <li class="nav-item <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                    <a href="./users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $current_page == 'role.php' ? 'active' : ''; ?>">
                    <a href="./role.php" class="nav-link">
                        <i class="fas fa-user-shield"></i>
                        <span class="nav-text">Roles</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($role_id == 1 || $role_id == 2 || $role_id == 3): // All roles ?>
                <li class="nav-item <?php echo $current_page == 'report.php' ? 'active' : ''; ?>">
                    <a href="./report.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <?php
            $userImage = isset($_SESSION['user_image']) ? trim($_SESSION['user_image']) : '';
            $userImagePath = '../uploads/users/' . rawurlencode($userImage);
            $userImageFallback = '../static/images/default-user.png';
            $userImageSrc = ($userImage !== '' && file_exists(__DIR__ . '/../../uploads/users/' . $userImage))
                ? $userImagePath
                : $userImageFallback;
            $userName = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
            ?>
            <img src="<?php echo htmlspecialchars($userImageSrc); ?>" alt="<?php echo htmlspecialchars($userName); ?>"
                class="profile-img">
            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
        </div>
    </div>
</aside>