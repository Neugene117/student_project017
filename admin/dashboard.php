<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Database connection
require_once '../config/db.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$is_admin = ($role_id === 1);

// --- Fetch Stats Cards ---

// Total / Assigned Equipment
if ($is_admin) {
    $sql = "SELECT COUNT(*) as count FROM equipment";
} else {
    $sql = "SELECT COUNT(DISTINCT equipment_id) as count FROM maintenance_schedule WHERE assigned_to_user_id = '$user_id'";
}
$result = mysqli_query($conn, $sql);
$total_equipment = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;
$equipment_card_label = $is_admin ? 'Total Equipment' : 'Total Assigned Equipment';

// Active Maintenance (Status is not Completed/Done/Finished)
if ($is_admin) {
    $sql = "SELECT COUNT(*) as count FROM maintenance WHERE statuss NOT IN ('Completed', 'Done', 'Finished', 'Cancelled')";
} else {
    $sql = "SELECT COUNT(*) as count 
            FROM maintenance m
            WHERE m.statuss NOT IN ('Completed', 'Done', 'Finished', 'Cancelled')
              AND (
                    m.user_id = '$user_id'
                    OR EXISTS (
                        SELECT 1
                        FROM maintenance_schedule ms
                        WHERE ms.equipment_id = m.equipment_id
                          AND ms.assigned_to_user_id = '$user_id'
                    )
              )";
}
$result = mysqli_query($conn, $sql);
$active_maintenance = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;

// Active Breakdowns (Status is not Fixed/Resolved/Closed)
if ($is_admin) {
    $sql = "SELECT COUNT(*) as count FROM breakdown WHERE statuss NOT IN ('Fixed', 'Resolved', 'Closed')";
} else {
    $sql = "SELECT COUNT(*) as count 
            FROM breakdown b
            WHERE b.statuss NOT IN ('Fixed', 'Resolved', 'Closed')
              AND (
                    b.reported_by_user_id = '$user_id'
                    OR EXISTS (
                        SELECT 1
                        FROM maintenance_schedule ms
                        WHERE ms.equipment_id = b.equipment_id
                          AND ms.assigned_to_user_id = '$user_id'
                    )
              )";
}
$result = mysqli_query($conn, $sql);
$active_breakdowns = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;

// System Users
$system_users = 0;
if ($is_admin) {
    $sql = "SELECT COUNT(*) as count FROM users";
    $result = mysqli_query($conn, $sql);
    $system_users = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;
}

// --- Secondary Stats ---
$total_categories = 0;
$total_locations = 0;
$total_schedules = 0;
$completed_today = 0;
if ($is_admin) {
    // Categories
    $sql = "SELECT COUNT(*) as count FROM category";
    $result = mysqli_query($conn, $sql);
    $total_categories = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;

    // Locations
    $sql = "SELECT COUNT(*) as count FROM equipment_location";
    $result = mysqli_query($conn, $sql);
    $total_locations = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;

    // Schedules
    $sql = "SELECT COUNT(*) as count FROM maintenance_schedule";
    $result = mysqli_query($conn, $sql);
    $total_schedules = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;

    // Completed Today
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as count FROM maintenance WHERE DATE(completed_date) = '$today' AND statuss = 'Completed'";
    $result = mysqli_query($conn, $sql);
    $completed_today = ($result) ? (int)mysqli_fetch_assoc($result)['count'] : 0;
}

// --- Charts Data ---

// Equipment Status
if ($is_admin) {
    $sql = "SELECT statuss, COUNT(*) as count FROM equipment GROUP BY statuss";
} else {
    $sql = "SELECT e.statuss, COUNT(DISTINCT e.id) as count
            FROM equipment e
            INNER JOIN maintenance_schedule ms ON ms.equipment_id = e.id
            WHERE ms.assigned_to_user_id = '$user_id'
            GROUP BY e.statuss";
}
$result = mysqli_query($conn, $sql);
$status_counts = ['Operational' => 0, 'Under Maintenance' => 0, 'Broken Down' => 0, 'Inactive' => 0]; // Default keys

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $status = $row['statuss'];
        $count = $row['count'];
        
        // Map DB status to Chart categories
        if (stripos($status, 'Operational') !== false || stripos($status, 'Active') !== false) {
            $status_counts['Operational'] += $count;
        } elseif (stripos($status, 'Maintenance') !== false || stripos($status, 'Repair') !== false) {
            $status_counts['Under Maintenance'] += $count;
        } elseif (stripos($status, 'Broken') !== false || stripos($status, 'Breakdown') !== false) {
            $status_counts['Broken Down'] += $count;
        } elseif (stripos($status, 'Inactive') !== false || stripos($status, 'Disposed') !== false) {
            $status_counts['Inactive'] += $count;
        } else {
             // Fallback to Inactive for unknown statuses to keep the chart clean
            $status_counts['Inactive'] += $count;
        }
    }
}
$equipment_chart_json = json_encode(array_values($status_counts));
$equipment_labels_json = json_encode(array_keys($status_counts));


// Maintenance Overview (Daily / Weekly / Monthly)
$chart_period = $_GET['chart_period'] ?? 'weekly';
$valid_chart_periods = ['daily', 'weekly', 'monthly'];
if (!in_array($chart_period, $valid_chart_periods, true)) {
    $chart_period = 'weekly';
}

function getMaintenanceOverviewData($conn, $period, $is_admin, $user_id) {
    $labels = [];
    $completed_counts = [];
    $scheduled_counts = [];
    $overdue_counts = [];

    $time_slots = [];
    if ($period === 'daily') {
        // Last 24 hours, grouped hourly
        for ($i = 23; $i >= 0; $i--) {
            $start = strtotime("-$i hours");
            $end = strtotime('+1 hour', $start);
            $time_slots[] = [
                'label' => date('H:00', $start),
                'start' => date('Y-m-d H:i:s', $start),
                'end' => date('Y-m-d H:i:s', $end)
            ];
        }
    } elseif ($period === 'monthly') {
        // Current month, grouped by day
        $days_in_month = (int)date('t');
        $current_ym = date('Y-m');
        for ($day = 1; $day <= $days_in_month; $day++) {
            $day_str = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
            $start = strtotime($current_ym . '-' . $day_str . ' 00:00:00');
            $end = strtotime('+1 day', $start);
            $time_slots[] = [
                'label' => (string)$day,
                'start' => date('Y-m-d H:i:s', $start),
                'end' => date('Y-m-d H:i:s', $end)
            ];
        }
    } else {
        // Last 7 days, grouped by day
        for ($i = 6; $i >= 0; $i--) {
            $start = strtotime(date('Y-m-d 00:00:00', strtotime("-$i days")));
            $end = strtotime('+1 day', $start);
            $time_slots[] = [
                'label' => date('D', $start),
                'start' => date('Y-m-d H:i:s', $start),
                'end' => date('Y-m-d H:i:s', $end)
            ];
        }
    }

    foreach ($time_slots as $slot) {
        $labels[] = $slot['label'];

        // Completed
        $sql = "SELECT COUNT(*) as count 
                FROM maintenance 
                WHERE completed_date >= '{$slot['start']}' 
                AND completed_date < '{$slot['end']}' 
                AND statuss IN ('Completed', 'Done', 'Finished')";
        if (!$is_admin) {
            $sql .= " AND (
                        user_id = '$user_id'
                        OR EXISTS (
                            SELECT 1
                            FROM maintenance_schedule ms
                            WHERE ms.equipment_id = maintenance.equipment_id
                              AND ms.assigned_to_user_id = '$user_id'
                        )
                      )";
        }
        $res = mysqli_query($conn, $sql);
        $completed_counts[] = ($res) ? (int)mysqli_fetch_assoc($res)['count'] : 0;

        // Scheduled
        $sql = "SELECT COUNT(*) as count 
                FROM maintenance_schedule 
                WHERE start_date >= '{$slot['start']}' 
                AND start_date < '{$slot['end']}'";
        if (!$is_admin) {
            $sql .= " AND assigned_to_user_id = '$user_id'";
        }
        $res = mysqli_query($conn, $sql);
        $scheduled_counts[] = ($res) ? (int)mysqli_fetch_assoc($res)['count'] : 0;

        // Overdue/Backlog as of the end of the slot
        $sql = "SELECT COUNT(*) as count 
                FROM maintenance 
                WHERE created_at < '{$slot['end']}' 
                AND statuss NOT IN ('Completed', 'Done', 'Finished', 'Cancelled')";
        if (!$is_admin) {
            $sql .= " AND (
                        user_id = '$user_id'
                        OR EXISTS (
                            SELECT 1
                            FROM maintenance_schedule ms
                            WHERE ms.equipment_id = maintenance.equipment_id
                              AND ms.assigned_to_user_id = '$user_id'
                        )
                      )";
        }
        $res = mysqli_query($conn, $sql);
        $overdue_counts[] = ($res) ? (int)mysqli_fetch_assoc($res)['count'] : 0;
    }

    return [
        'labels' => $labels,
        'completed' => $completed_counts,
        'scheduled' => $scheduled_counts,
        'overdue' => $overdue_counts
    ];
}

$maintenance_overview_data = [
    'daily' => getMaintenanceOverviewData($conn, 'daily', $is_admin, $user_id),
    'weekly' => getMaintenanceOverviewData($conn, 'weekly', $is_admin, $user_id),
    'monthly' => getMaintenanceOverviewData($conn, 'monthly', $is_admin, $user_id)
];

$selected_overview = $maintenance_overview_data[$chart_period];
$maintenance_dates_json = json_encode($selected_overview['labels']);
$maintenance_completed_json = json_encode($selected_overview['completed']);
$maintenance_scheduled_json = json_encode($selected_overview['scheduled']);
$maintenance_overdue_json = json_encode($selected_overview['overdue']);
$maintenance_overview_json = json_encode($maintenance_overview_data);
$maintenance_period_json = json_encode($chart_period);


// --- Recent Activities ---
$recent_activities = [];

// Fetch latest from Notification table
if ($is_admin) {
    $sql = "SELECT * FROM notification ORDER BY created_at DESC LIMIT 5";
} else {
    $sql = "SELECT * FROM notification WHERE user_id = '$user_id' OR user_id IS NULL ORDER BY created_at DESC LIMIT 5";
}
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
}

// If no notifications, fetch latest breakdowns and maintenance
if (empty($recent_activities)) {
    // Fetch latest breakdowns
    if ($is_admin) {
        $sql = "SELECT 'breakdown' as type, issue_description as message, create_at as created_at FROM breakdown ORDER BY create_at DESC LIMIT 3";
    } else {
        $sql = "SELECT 'breakdown' as type, b.issue_description as message, b.create_at as created_at
                FROM breakdown b
                WHERE b.reported_by_user_id = '$user_id'
                   OR EXISTS (
                        SELECT 1
                        FROM maintenance_schedule ms
                        WHERE ms.equipment_id = b.equipment_id
                          AND ms.assigned_to_user_id = '$user_id'
                   )
                ORDER BY b.create_at DESC LIMIT 3";
    }
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $recent_activities[] = $row;
        }
    }
    
    // Fetch latest maintenance
    if ($is_admin) {
        $sql = "SELECT 'maintenance' as type, description as message, completed_date as created_at FROM maintenance WHERE completed_date IS NOT NULL ORDER BY completed_date DESC LIMIT 3";
    } else {
        $sql = "SELECT 'maintenance' as type, m.description as message, m.completed_date as created_at
                FROM maintenance m
                WHERE m.completed_date IS NOT NULL
                  AND (
                        m.user_id = '$user_id'
                        OR EXISTS (
                            SELECT 1
                            FROM maintenance_schedule ms
                            WHERE ms.equipment_id = m.equipment_id
                              AND ms.assigned_to_user_id = '$user_id'
                        )
                  )
                ORDER BY m.completed_date DESC LIMIT 3";
    }
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $recent_activities[] = $row;
        }
    }
    
    // Sort combined
    usort($recent_activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recent_activities = array_slice($recent_activities, 0, 5);
}

// --- Critical Alerts ---
$alerts = [];

// High Priority Breakdowns
if ($is_admin) {
    $sql = "SELECT * FROM breakdown WHERE priority = 'High' AND statuss NOT IN ('Fixed', 'Resolved') LIMIT 3";
} else {
    $sql = "SELECT b.*
            FROM breakdown b
            WHERE b.priority = 'High'
              AND b.statuss NOT IN ('Fixed', 'Resolved')
              AND (
                    b.reported_by_user_id = '$user_id'
                    OR EXISTS (
                        SELECT 1
                        FROM maintenance_schedule ms
                        WHERE ms.equipment_id = b.equipment_id
                          AND ms.assigned_to_user_id = '$user_id'
                    )
              )
            LIMIT 3";
}
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $alerts[] = [
            'type' => 'high',
            'title' => 'High Priority Breakdown',
            'message' => $row['issue_description'],
            'time' => $row['create_at']
        ];
    }
}

// Warranty Expiring (Next 30 days)
if ($is_admin) {
    $sql = "SELECT * FROM equipment WHERE expired_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) LIMIT 3";
} else {
    $sql = "SELECT e.*
            FROM equipment e
            INNER JOIN maintenance_schedule ms ON ms.equipment_id = e.id
            WHERE ms.assigned_to_user_id = '$user_id'
              AND e.expired_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
            GROUP BY e.id
            LIMIT 3";
}
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $alerts[] = [
            'type' => 'low',
            'title' => 'Warranty Expiring',
            'message' => $row['equipment_name'] . ' warranty expires soon.',
            'time' => $row['updated_at']
        ];
    }
}

// --- Top Performing Technicians ---
$top_technicians = [];
if ($is_admin) {
    $sql = "SELECT u.firstname, u.lastname, u.user_id, COUNT(m.mid) as completed_count 
            FROM users u 
            JOIN maintenance m ON u.user_id = m.user_id 
            WHERE m.statuss = 'Completed' 
            GROUP BY u.user_id 
            ORDER BY completed_count DESC 
            LIMIT 5";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $top_technicians[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
</head>
<body>
   <?php include './include/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <?php include './include/header.php'; ?>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <p class="welcome-text">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's your equipment management system overview.</p>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon equipment">
                        <i class="fas fa-laptop-medical"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value"><?php echo $total_equipment; ?></h3>
                        <p class="stat-label"><?php echo htmlspecialchars($equipment_card_label); ?></p>
                    </div>
                    <div class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i> Active
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon maintenance">
                        <i class="fas fa-wrench"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value"><?php echo $active_maintenance; ?></h3>
                        <p class="stat-label">Active Maintenance</p>
                    </div>
                    <div class="stat-trend warning-trend">
                        <i class="fas fa-clock"></i> In Progress
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon breakdown">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value"><?php echo $active_breakdowns; ?></h3>
                        <p class="stat-label">Active Breakdowns</p>
                    </div>
                    <div class="stat-trend danger">
                        <i class="fas fa-exclamation-circle"></i> Needs Action
                    </div>
                </div>

                <?php if ($is_admin): ?>
                    <div class="stat-card">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value"><?php echo $system_users; ?></h3>
                            <p class="stat-label">System Users</p>
                        </div>
                        <div class="stat-trend positive">
                            <i class="fas fa-user-check"></i> Registered
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($is_admin): ?>
                <!-- Additional Stats Row -->
                <div class="stats-grid secondary-stats">
                    <div class="stat-card small">
                        <div class="stat-icon-small categories">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value"><?php echo $total_categories; ?></h3>
                            <p class="stat-label">Categories</p>
                        </div>
                    </div>

                    <div class="stat-card small">
                        <div class="stat-icon-small locations">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value"><?php echo $total_locations; ?></h3>
                            <p class="stat-label">Locations</p>
                        </div>
                    </div>

                    <div class="stat-card small">
                        <div class="stat-icon-small schedules">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value"><?php echo $total_schedules; ?></h3>
                            <p class="stat-label">Schedules</p>
                        </div>
                    </div>

                    <div class="stat-card small">
                        <div class="stat-icon-small completed">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value"><?php echo $completed_today; ?></h3>
                            <p class="stat-label">Completed Today</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Chart Section -->
                <div class="card chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Maintenance Overview</h3>
                        <div class="card-actions">
                            <select class="chart-filter" id="maintenance-period-filter">
                                <option value="daily" <?php echo $chart_period === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $chart_period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $chart_period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                            <button class="icon-btn">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="maintenanceChart"></canvas>
                    </div>
                </div>

                <!-- Equipment Status -->
                <div class="card status-card">
                    <div class="card-header">
                        <h3><i class="fas fa-heartbeat"></i> Equipment Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="status-chart">
                            <canvas id="equipmentStatusChart"></canvas>
                        </div>
                        <div class="status-legend">
                            <?php foreach ($status_counts as $status => $count): ?>
                            <div class="legend-item">
                                <?php 
                                    $dotClass = 'inactive';
                                    if (stripos($status, 'Operational') !== false) $dotClass = 'operational';
                                    elseif (stripos($status, 'Maintenance') !== false) $dotClass = 'maintenance-dot';
                                    elseif (stripos($status, 'Broken') !== false) $dotClass = 'broken';
                                ?>
                                <span class="legend-dot <?php echo $dotClass; ?>"></span>
                                <span class="legend-text"><?php echo htmlspecialchars($status); ?></span>
                                <span class="legend-value"><?php echo $count; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities & Alerts -->
            <div class="content-grid">
                <!-- Recent Activities -->
                <div class="card activity-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Activities</h3>
                        <a href="notifications.php" class="view-all">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php if (empty($recent_activities)): ?>
                                <p class="activity-text" style="padding: 10px;">No recent activities.</p>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <?php 
                                        $type = strtolower($activity['type']);
                                        $icon = 'fa-info-circle';
                                        $iconClass = 'equipment'; // default
                                        
                                        if (strpos($type, 'breakdown') !== false) {
                                            $icon = 'fa-exclamation-triangle';
                                            $iconClass = 'breakdown';
                                        } elseif (strpos($type, 'maintenance') !== false) {
                                            $icon = 'fa-wrench';
                                            $iconClass = 'maintenance';
                                        } elseif (strpos($type, 'system') !== false) {
                                            $icon = 'fa-server';
                                            $iconClass = 'equipment';
                                        }
                                    ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?php echo $iconClass; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p class="activity-text"><?php echo htmlspecialchars($activity['message']); ?></p>
                                            <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Critical Alerts -->
                <div class="card alerts-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Critical Alerts</h3>
                    </div>
                    <div class="card-body">
                        <div class="alerts-list">
                            <?php if (empty($alerts)): ?>
                                <p style="padding: 10px; color: #666;">No critical alerts.</p>
                            <?php else: ?>
                                <?php foreach ($alerts as $alert): ?>
                                    <div class="alert-item <?php echo $alert['type']; ?>">
                                        <div class="alert-icon">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <div class="alert-content">
                                            <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                                            <span class="alert-time"><?php echo isset($alert['time']) ? timeAgo($alert['time']) : 'Just now'; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Top Technicians -->
            <div class="content-grid">
                <!-- Quick Actions -->
                <div class="card quick-actions-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <?php if ($is_admin): ?>
                                <a href="equipment.php" class="action-btn">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Add Equipment</span>
                                </a>
                                <a href="schedules.php" class="action-btn">
                                    <i class="fas fa-wrench"></i>
                                    <span>Schedule Maintenance</span>
                                </a>
                                <a href="breakdowns.php" class="action-btn">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Report Breakdown</span>
                                </a>
                                <a href="users.php" class="action-btn">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Add User</span>
                                </a>
                                <a href="categories.php" class="action-btn">
                                    <i class="fas fa-layer-group"></i>
                                    <span>Add Category</span>
                                </a>
                                <a href="equipment_location.php" class="action-btn">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Add Location</span>
                                </a>
                            <?php else: ?>
                                <a href="schedules_technician.php" class="action-btn">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>My Schedules</span>
                                </a>
                                <a href="breakdowns.php" class="action-btn">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Report Breakdown</span>
                                </a>
                                <a href="notifications.php" class="action-btn">
                                    <i class="fas fa-bell"></i>
                                    <span>View Notifications</span>
                                </a>
                                <a href="profile.php" class="action-btn">
                                    <i class="fas fa-user"></i>
                                    <span>My Profile</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                    <!-- Top Technicians -->
                    <div class="card top-technicians-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-cog"></i> Top Technicians</h3>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <?php if (empty($top_technicians)): ?>
                                    <p class="activity-text" style="padding: 10px;">No data available.</p>
                                <?php else: ?>
                                    <?php foreach ($top_technicians as $tech): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon user">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                            <div class="activity-content">
                                                <p class="activity-text"><strong><?php echo htmlspecialchars($tech['firstname'] . ' ' . $tech['lastname']); ?></strong></p>
                                                <span class="activity-time"><?php echo $tech['completed_count']; ?> Tasks Completed</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Pass PHP Data to JS -->
    <script>
        // Data from PHP
        var equipmentStatusData = <?php echo $equipment_chart_json; ?>;
        var equipmentStatusLabels = <?php echo $equipment_labels_json; ?>;
        
        var maintenanceDates = <?php echo $maintenance_dates_json; ?>;
        var maintenanceCompleted = <?php echo $maintenance_completed_json; ?>;
        var maintenanceScheduled = <?php echo $maintenance_scheduled_json; ?>;
        var maintenanceOverdue = <?php echo $maintenance_overdue_json; ?>;
        var maintenanceDatasets = <?php echo $maintenance_overview_json; ?>;
        var maintenancePeriod = <?php echo $maintenance_period_json; ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="./assets/js/script.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Check for error or success messages in URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const errorMsg = urlParams.get('error');
        const successMsg = urlParams.get('success');

        if (errorMsg) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            }).then(() => {
                // Optional: Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        }

        if (successMsg) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: successMsg,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            }).then(() => {
                // Optional: Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        }
    </script>
</body>
</html>
