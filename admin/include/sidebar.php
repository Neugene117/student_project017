 <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img class="logo-img" src="./../static/images/logo.JPG" alt="logo">
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item active">
                    <a href="#" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-laptop-medical"></i>
                        <span class="nav-text">Equipment</span>
                        <span class="badge">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-wrench"></i>
                        <span class="nav-text">Maintenance</span>
                        <span class="badge warning">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Schedules</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="nav-text">Breakdowns</span>
                        <span class="badge danger">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="./pages/categories.php" class="nav-link">
                        <i class="fas fa-layer-group"></i>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="nav-text">Locations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-user-shield"></i>
                        <span class="nav-text">Roles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>           
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
                <img src="<?php echo htmlspecialchars($userImageSrc); ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="profile-img">
                <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
            </div>
        </div>
    </aside>
