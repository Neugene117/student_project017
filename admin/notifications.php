<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Database connection
include('../config/db.php');

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? null;
$role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
$can_delete_notifications = ($role_id !== 2); // Technician (role 2) cannot delete notifications

// Handle notification deletion
if (isset($_POST['delete_notification'])) {
    if (!$can_delete_notifications) {
        $_SESSION['error_message'] = "You are not allowed to delete notifications";
        header("Location: notifications.php");
        exit();
    }

    $notification_id = intval($_POST['notification_id']);
    // Only allow deletion if the notification belongs to the current user
    $delete_query = "DELETE FROM notification WHERE notification_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $_SESSION['success_message'] = "Notification deleted successfully";
        } else {
            $_SESSION['error_message'] = "Notification not found or access denied";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Failed to delete notification";
    }
    header("Location: notifications.php");
    exit();
}

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['notification_id']);
    // Only allow marking as read if the notification belongs to the user
    $update_query = "UPDATE notification SET is_read = 1 WHERE notification_id = '$notification_id' AND user_id = '$user_id'";
    mysqli_query($conn, $update_query);
    header("Location: notifications.php");
    exit();
}

// Handle mark all as read (kept for backward compatibility or manual trigger if needed, though auto-read handles it)
if (isset($_POST['mark_all_read'])) {
    $update_query = "UPDATE notification SET is_read = 1 WHERE user_id = '$user_id'";
    mysqli_query($conn, $update_query);
    header("Location: notifications.php");
    exit();
}

// Automatically mark all notifications as read when page is opened
if ($user_id) {
    $update_query = "UPDATE notification SET is_read = 1 WHERE user_id = '$user_id' AND is_read = 0";
    mysqli_query($conn, $update_query);
}

// Fetch notifications
$notifications = [];
$unread_count = 0; // Will be 0 since we just marked them as read

if ($user_id) {
    // Get all notifications for the user
    $query = "SELECT * FROM notification WHERE user_id = '$user_id' ORDER BY created_at DESC";
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
        // No need to count unread since we just marked them all as read
    }
}

// Get notification counts by type
$total_count = count($notifications);
$breakdown_count = 0;
$maintenance_count = 0;
$system_count = 0;

foreach ($notifications as $notification) {
    switch ($notification['type']) {
        case 'breakdown':
            $breakdown_count++;
            break;
        case 'maintenance':
            $maintenance_count++;
            break;
        case 'system':
            $system_count++;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <link rel="stylesheet" href="./assets/css/notification.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
    <?php include './include/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <?php include './include/header.php'; ?>

        <!-- Notifications Content -->
        <div class="dashboard-content">
            <div class="notifications-header">
                <h1>
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </h1>
                <div class="header-actions">
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="mark_all_read" class="mark-read-btn">
                                <i class="fas fa-check-double"></i>
                                Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon equipment">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value"><?php echo $total_count; ?></h3>
                        <p class="stat-label">Total Notifications</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon maintenance">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value"><?php echo $unread_count; ?></h3>
                        <p class="stat-label">Unread</p>
                    </div>
                    <?php if ($unread_count > 0): ?>
                        <div class="stat-trend danger">
                            <i class="fas fa-exclamation-circle"></i> New
                        </div>
                    <?php endif; ?>
                </div>

                <div class="stat-card">
                    <div class="stat-icon breakdown">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value"><?php echo $breakdown_count; ?></h3>
                        <p class="stat-label">Breakdown Alerts</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-wrench"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value"><?php echo $maintenance_count; ?></h3>
                        <p class="stat-label">Maintenance Alerts</p>
                    </div>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Notifications</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h3>No Notifications</h3>
                            <p>You don't have any notifications yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                <div class="notification-icon <?php echo htmlspecialchars($notification['type']); ?>">
                                    <i class="fas <?php echo getNotificationIcon($notification['type']); ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    <span class="notification-time">
                                        <i class="far fa-clock"></i> 
                                        <?php echo timeAgo($notification['created_at']); ?>
                                    </span>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                            <button type="submit" name="mark_read" class="notification-btn read" title="Mark as read">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($can_delete_notifications): ?>
                                        <button type="button" class="notification-btn delete" 
                                                onclick="confirmDelete(<?php echo $notification['notification_id']; ?>)" 
                                                title="Delete notification">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="./assets/js/script.js"></script>                                   
    <script>
        function confirmDelete(notificationId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to delete this notification?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'notifications.php';
                    
                    const input1 = document.createElement('input');
                    input1.type = 'hidden';
                    input1.name = 'notification_id';
                    input1.value = notificationId;
                    
                    const input2 = document.createElement('input');
                    input2.type = 'hidden';
                    input2.name = 'delete_notification';
                    input2.value = '1';
                    
                    form.appendChild(input1);
                    form.appendChild(input2);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Show success/error messages
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $_SESSION['success_message']; ?>',
                timer: 2000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo $_SESSION['error_message']; ?>',
                timer: 2000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
