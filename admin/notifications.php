<?php
session_start();

// Include database connection
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle mark as read action
if (isset($_POST['mark_all_read']) && $_POST['mark_all_read'] == 1) {
    $update_query = "UPDATE notification SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $update_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
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

// Fetch all notifications
$notifications = [];
$unread_count = 0;

$query = "SELECT notification_id, message, type, is_read, created_at 
          FROM notification 
          WHERE user_id = ? OR user_id IS NULL 
          ORDER BY created_at DESC";
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

// Get user info
$userName = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'User Account';
$userImage = isset($_SESSION['user_image']) ? trim($_SESSION['user_image']) : '';
$userImagePath = '../uploads/users/' . rawurlencode($userImage);
$userImageFallback = '../static/images/default-user.png';
$userImageSrc = ($userImage !== '' && file_exists(__DIR__ . '/../../uploads/users/' . $userImage))
    ? $userImagePath
    : $userImageFallback;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
            background: var(--gray-50);
            transition: var(--transition);
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        .notifications-page {
            max-width: 900px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 24px;
            color: var(--gray-900);
        }

        .mark-all-btn {
            padding: 10px 20px;
            background: var(--primary-red);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition);
        }

        .mark-all-btn:hover {
            background: var(--primary-red-dark);
        }

        .notifications-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .notification-full-item {
            display: flex;
            gap: 16px;
            padding: 20px;
            border-bottom: 1px solid var(--gray-100);
            transition: var(--transition);
        }

        .notification-full-item:hover {
            background: var(--gray-50);
        }

        .notification-full-item.unread {
            background: var(--green-50);
        }

        .notification-full-item.unread:hover {
            background: #dcfce7;
        }

        .mark-read-btn {
            align-self: flex-start;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-500);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .mark-read-btn:hover {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .notification-full-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notification-full-icon.breakdown {
            background: var(--danger-light);
            color: var(--danger);
        }

        .notification-full-icon.maintenance {
            background: var(--warning-light);
            color: var(--warning);
        }

        .notification-full-icon.expired {
            background: #fce7f3;
            color: #ec4899;
        }

        .notification-full-icon.user {
            background: var(--info-light);
            color: var(--info);
        }

        .notification-full-icon.success {
            background: var(--success-light);
            color: var(--success);
        }

        .notification-full-icon.warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .notification-full-icon.info {
            background: var(--info-light);
            color: var(--info);
        }

        .notification-full-icon.error {
            background: var(--danger-light);
            color: var(--danger);
        }

        .notification-full-icon.default {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .notification-full-content {
            flex: 1;
        }

        .notification-full-text {
            font-size: 15px;
            color: var(--gray-700);
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .notification-full-time {
            font-size: 13px;
            color: var(--gray-500);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        .empty-state p {
            font-size: 16px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            background: var(--danger);
            color: white;
            font-size: 11px;
            border-radius: 10px;
            margin-left: 8px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.collapsed ~ .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../include/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="notifications-page">
            <div class="page-header">
                <h1>Notifications <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?> unread</span><?php endif; ?></h1>
                <?php if ($unread_count > 0): ?>
                <form method="POST" action="">
                    <button type="submit" name="mark_all_read" value="1" class="mark-all-btn">
                        <i class="fas fa-check-double"></i> Mark all as read
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="notifications-container">
                <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-full-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                        <form method="POST" action="">
                            <input type="hidden" name="mark_single_read" value="1">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                            <button type="submit" class="mark-read-btn" title="Mark as read">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <div class="notification-full-icon <?php echo htmlspecialchars($notification['type']); ?>">
                            <i class="fas <?php echo getNotificationIcon($notification['type']); ?>"></i>
                        </div>
                        <div class="notification-full-content">
                            <p class="notification-full-text"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <span class="notification-full-time"><?php echo timeAgo($notification['created_at']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
