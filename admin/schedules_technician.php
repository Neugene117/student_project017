<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Check if user has technician role
$user_role = isset($_SESSION['user_role']) ? trim($_SESSION['user_role']) : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$is_technician = ($user_role === 'technician');

// If user is not technician, redirect them
if (!$is_technician) {
    header("Location: ./dashboard.php?error=" . urlencode("You do not have permission to access this page"));
    exit();
}

// Include database connection
include('../config/db.php');

// Fetch schedules assigned to the logged-in technician
$schedules = [];
$sql = "SELECT 
            ms.schedule_id,
            ms.equipment_id,
            ms.maintenance_type,
            ms.interval_value,
            ms.interval_unit,
            ms.start_date,
            ms.assigned_to_user_id,
            ms.created_at,
            ms.updated_at,
            e.equipment_name,
            e.equipment_image
        FROM maintenance_schedule ms
        LEFT JOIN equipment e ON ms.equipment_id = e.id
        WHERE ms.assigned_to_user_id = ?
        ORDER BY ms.start_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
$stmt->close();

// Function to calculate next maintenance date based on created_at
function getNextMaintenanceDate($created_at_str, $interval_value, $interval_unit) {
    $created_at = new DateTime($created_at_str);
    $current_time = new DateTime('now');
    
    // Define interval in seconds
    $interval_seconds = [
        'SECOND' => 1,
        'MINUTE' => 60,
        'HOUR' => 3600,
        'DAY' => 86400,
        'MONTH' => 2592000,  // 30 days
        'YEAR' => 31536000   // 365 days
    ];
    
    $total_seconds = $interval_value * ($interval_seconds[$interval_unit] ?? 86400);
    
    // Calculate how many intervals have passed since created_at
    $interval_passed = $current_time->getTimestamp() - $created_at->getTimestamp();
    
    if ($interval_passed < 0) {
        // Schedule hasn't started yet
        return [
            'next_date' => $created_at,
            'is_due' => false,
            'seconds_until_due' => abs($interval_passed)
        ];
    }
    
    // Calculate how many complete intervals have passed
    $completed_intervals = floor($interval_passed / $total_seconds);
    
    // Calculate next maintenance date (first occurrence is at created_at + interval)
    $next_maintenance = clone $created_at;
    
    if ($interval_unit === 'MONTH') {
        $next_maintenance->modify('+' . ($completed_intervals + 1) . ' months');
    } elseif ($interval_unit === 'YEAR') {
        $next_maintenance->modify('+' . ($completed_intervals + 1) . ' years');
    } else {
        $next_maintenance->modify('+' . (($completed_intervals + 1) * $interval_value) . ' ' . strtolower($interval_unit) . 's');
    }
    
    // Check if next maintenance is due (has passed)
    $is_due = $current_time >= $next_maintenance;
    $seconds_until_due = $next_maintenance->getTimestamp() - $current_time->getTimestamp();
    
    return [
        'next_date' => $next_maintenance,
        'is_due' => $is_due,
        'seconds_until_due' => max(0, $seconds_until_due)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Schedules - Technician</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .schedules-table {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .schedules-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedules-table thead {
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }

        .schedules-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 14px;
        }

        .schedules-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-size: 14px;
        }

        .schedules-table tbody tr:hover {
            background: var(--gray-50);
        }

        .schedules-table tbody tr:last-child td {
            border-bottom: none;
        }

        .schedule-id {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .created-date {
            font-size: 13px;
            color: var(--gray-500);
        }

        .btn-action {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-action.disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .btn-action.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .btn-action.hidden {
            display: none;
        }

        .equipment-image-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .equipment-image {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            background: #f3f4f6;
        }

        .equipment-image:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            transform: scale(1.05);
        }

        .equipment-info h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .equipment-info p {
            margin: 0;
            font-size: 12px;
            color: var(--gray-500);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--gray-300);
            margin-bottom: 16px;
            display: block;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: 30%;
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat-mini-card {
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-mini-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: #f0f9ff;
            color: var(--primary-blue);
        }

        .stat-mini-content h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-800);
        }

        .stat-mini-content p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: var(--gray-600);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.due {
            background: #fecaca;
            color: #991b1b;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        @media (max-width: 768px) {
            .schedules-table {
                overflow-x: auto;
            }

            .schedules-table table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include './include/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <?php include './include/header.php'; ?>

        <!-- Page Content -->
        <div class="dashboard-content">
            <!-- Stats Mini -->
            <div class="stats-mini">
                <div class="stat-mini-card">
                    <div class="stat-mini-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h3><?php echo count($schedules); ?></h3>
                        <p>Assigned Schedules</p>
                    </div>
                </div>
            </div>

            <!-- Schedules Table -->
            <div class="schedules-table">
                <?php if (count($schedules) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Equipment</th>
                                <th>Maintenance Type</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($schedules as $schedule): 
                                $maintenance_info = getNextMaintenanceDate(
                                    $schedule['created_at'],
                                    $schedule['interval_value'],
                                    $schedule['interval_unit']
                                );
                                $is_due = $maintenance_info['is_due'];
                                $next_date = $maintenance_info['next_date'];
                                $seconds_until = $maintenance_info['seconds_until_due'];
                            ?>
                                <tr>
                                    <td class="schedule-id">#<?php echo $counter; ?></td>
                                    <td>
                                        <div class="equipment-image-cell">
                                            <?php if (!empty($schedule['equipment_image'])): ?>
                                                <img src="../uploads/equipment/<?php echo htmlspecialchars($schedule['equipment_image']); ?>" alt="<?php echo htmlspecialchars($schedule['equipment_name']); ?>" class="equipment-image">
                                            <?php else: ?>
                                                <div class="equipment-image" style="display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #d1d5db;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="equipment-info">
                                                <h4><?php echo htmlspecialchars($schedule['equipment_name'] ?? 'N/A'); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($schedule['maintenance_type']); ?></td>
                                    <td>
                                        <span class="created-date">
                                            <?php echo date('M d, Y', strtotime($schedule['start_date'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $is_due ? 'due' : 'pending'; ?>" data-schedule-id="<?php echo $schedule['schedule_id']; ?>" data-seconds="<?php echo $seconds_until; ?>">
                                            <i class="fas <?php echo $is_due ? 'fa-exclamation-circle' : 'fa-clock'; ?>"></i>
                                            <?php if ($is_due): ?>
                                                <span>Due Now</span>
                                            <?php else: ?>
                                                <span class="countdown-text">Due in <span class="countdown-value">--:--:--</span></span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-action <?php echo !$is_due ? 'hidden' : ''; ?>" data-schedule-id="<?php echo $schedule['schedule_id']; ?>" title="Fill this maintenance schedule">
                                            <i class="fas fa-check"></i> Fill
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                            $counter++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Schedules Assigned</h3>
                        <p>You have no maintenance schedules assigned to you at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="./assets/js/script.js"></script>
    <script>
        // Store schedules data for real-time updates
        const schedules = [
            <?php foreach ($schedules as $schedule): 
                $maintenance_info = getNextMaintenanceDate(
                    $schedule['created_at'],
                    $schedule['interval_value'],
                    $schedule['interval_unit']
                );
            ?>
            {
                id: <?php echo $schedule['schedule_id']; ?>,
                start_date: '<?php echo htmlspecialchars($schedule['start_date']); ?>',
                interval_value: <?php echo $schedule['interval_value']; ?>,
                interval_unit: '<?php echo htmlspecialchars($schedule['interval_unit']); ?>',
                next_date: '<?php echo $maintenance_info['next_date']->format('Y-m-d H:i:s'); ?>'
            },
            <?php endforeach; ?>
        ];

        // Function to convert interval to seconds
        function getIntervalSeconds(interval_value, interval_unit) {
            const intervals = {
                'SECOND': 1,
                'MINUTE': 60,
                'HOUR': 3600,
                'DAY': 86400,
                'MONTH': 2592000,  // 30 days
                'YEAR': 31536000   // 365 days
            };
            return interval_value * (intervals[interval_unit] || 86400);
        }

        // Function to format time remaining
        function formatTimeRemaining(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // Update countdowns in real-time
        function updateScheduleStatus() {
            const now = new Date();
            
            schedules.forEach(schedule => {
                const nextDate = new Date(schedule.next_date);
                const badge = document.querySelector(`.status-badge[data-schedule-id="${schedule.id}"]`);
                const button = document.querySelector(`.btn-action[data-schedule-id="${schedule.id}"]`);
                
                if (!badge || !button) return;
                
                const secondsUntil = Math.max(0, Math.floor((nextDate - now) / 1000));
                const isDue = secondsUntil === 0;
                
                if (isDue) {
                    // Schedule is due
                    badge.classList.add('due');
                    badge.classList.remove('pending');
                    badge.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Due Now</span>';
                    button.classList.remove('hidden');
                } else {
                    // Schedule is pending
                    badge.classList.add('pending');
                    badge.classList.remove('due');
                    const timeFormatted = formatTimeRemaining(secondsUntil);
                    badge.innerHTML = `<i class="fas fa-clock"></i><span>Due in <span class="countdown-value">${timeFormatted}</span></span>`;
                    button.classList.add('hidden');
                }
            });
        }

        // Update every second
        setInterval(updateScheduleStatus, 1000);

        // Initial update
        updateScheduleStatus();

        // Handle Fill button click
        document.querySelectorAll('.btn-action').forEach(button => {
            button.addEventListener('click', function() {
                const scheduleId = this.getAttribute('data-schedule-id');
                alert(`Marking schedule ${scheduleId} as filled. This will be implemented soon.`);
                // TODO: Implement actual fill functionality
            });
        });
    </script>
</body>
</html>
