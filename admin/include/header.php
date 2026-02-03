
        <?php
            $userImage = isset($_SESSION['user_image']) ? trim($_SESSION['user_image']) : '';
            $userImagePath = '../uploads/users/' . rawurlencode($userImage);
            $userImageFallback = '../static/images/default-user.png';
            $userImageSrc = ($userImage !== '' && file_exists(__DIR__ . '/../../uploads/users/' . $userImage))
                ? $userImagePath
                : $userImageFallback;
            $userName = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
            $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'User Account';
            
            // Include database connection
            require_once '../config/db.php';
            
            // Get user_id from session
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            // Handle mark as read action
            if (isset($_POST['mark_all_read']) && $_POST['mark_all_read'] == 1) {
                if ($user_id) {
                    $update_query = "UPDATE notification SET is_read = 1 WHERE user_id = ? AND is_read = 0";
                    $stmt = mysqli_prepare($conn, $update_query);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
            }
            
            // Handle single notification mark as read
            if (isset($_POST['mark_single_read']) && isset($_POST['notification_id'])) {
                $notification_id = (int)$_POST['notification_id'];
                $update_query = "UPDATE notification SET is_read = 1 WHERE notification_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $notification_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
            
            // Fetch notifications
            $notifications = [];
            $unread_count = 0;
            
            $query = "SELECT notification_id, message, type, is_read, created_at 
                      FROM notification 
                      WHERE user_id = ? OR user_id IS NULL 
                      ORDER BY created_at DESC LIMIT 10";
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $notifications[] = $row;
                    if ($row['is_read'] == 0) {
                        $unread_count++;
                    }
                }
                mysqli_stmt_close($stmt);
            }
            
            // Function to get time ago
            function timeAgo($timestamp) {
                $time_ago = strtotime($timestamp);
                $current_time = time();
                $time_difference = $current_time - $time_ago;
                $seconds = $time_difference;
                $minutes = round($seconds / 60);
                $hours = round($seconds / 3600);
                $days = round($seconds / 86400);
                $weeks = round($seconds / 604800);
                $months = round($seconds / 2629800);
                $years = round($seconds / 31536000);
                
                if ($seconds <= 60) {
                    return "Just now";
                } elseif ($minutes <= 60) {
                    return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
                } elseif ($hours <= 24) {
                    return $hours == 1 ? "1 hour ago" : "$hours hours ago";
                } elseif ($days <= 7) {
                    return $days == 1 ? "1 day ago" : "$days days ago";
                } elseif ($weeks <= 4) {
                    return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
                } elseif ($months <= 12) {
                    return $months == 1 ? "1 month ago" : "$months months ago";
                } else {
                    return $years == 1 ? "1 year ago" : "$years years ago";
                }
            }
            
            // Function to get icon based on type
            function getNotificationIcon($type) {
                $icons = [
                    'breakdown' => 'fa-exclamation-circle',
                    'maintenance' => 'fa-wrench',
                    'expired' => 'fa-calendar-times',
                    'user' => 'fa-user-plus',
                    'success' => 'fa-check-circle',
                    'warning' => 'fa-exclamation-triangle',
                    'info' => 'fa-info-circle',
                    'error' => 'fa-times-circle'
                ];
                return isset($icons[$type]) ? $icons[$type] : 'fa-bell';
            }
        ?>

        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="toggle-btn" id="toggleBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Dashboard Overview</h1>
            </div>
            
            <div class="header-right">
                <!-- Notifications -->
                <div class="notification-wrapper">
                    <button class="notification-btn" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="notification-count"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <form method="POST" action="">
                            <div class="dropdown-header">
                                <h3>Notifications</h3>
                                <?php if ($unread_count > 0): ?>
                                <button type="submit" name="mark_all_read" value="1" class="mark-read">Mark all as read</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <div class="notification-list">
                            <?php if (empty($notifications)): ?>
                                <div class="notification-item">
                                    <div class="notification-content">
                                        <p class="notification-text">No notifications</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                                    <form method="POST" action="" class="mark-single-read-form">
                                        <input type="hidden" name="mark_single_read" value="1">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                        <button type="submit" class="mark-single-btn" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <div class="notification-icon <?php echo htmlspecialchars($notification['type']); ?>">
                                        <i class="fas <?php echo getNotificationIcon($notification['type']); ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p class="notification-text"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-footer">
                            <a href="notifications.php">View all notifications</a>
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <div class="profile-wrapper">
                    <?php
                        $display_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User';
                        $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($display_name) . "&background=2563eb&color=fff";
                    ?>
                    <button class="profile-btn" id="profileBtn">
                        <img src="<?php echo htmlspecialchars($userImageSrc); ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="profile-img">
                        <span class="profile-name"><?php echo htmlspecialchars($userName); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-header">
                            <img src="<?php echo htmlspecialchars($userImageSrc); ?>" alt="<?php echo htmlspecialchars($userName); ?>">
                            <div class="profile-info">
                                <h4><?php echo htmlspecialchars($userName); ?></h4>
                                <p><?php echo htmlspecialchars($userRole); ?></p>
                            </div>
                        </div>
                        <ul class="profile-menu">
                            <li>
                                <a href="./profile.php">
                                    <i class="fas fa-user"></i>
                                    <span>My Profile</span>
                                </a>
                            </li>
                            
                            <li class="divider"></li>
                            <li>
                                <a href="logout.php" class="logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>
