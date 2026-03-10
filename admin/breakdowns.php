<?php
session_start();

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html");
    exit();
}

include('../config/db.php');

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

// Get admin role_id
$admin_role_id = null;
$role_sql = "SELECT role_id FROM role WHERE role_name = 'Admin' LIMIT 1";
$role_result = $conn->query($role_sql);
if ($role_result && $role_result->num_rows > 0) {
    $role_row = $role_result->fetch_assoc();
    $admin_role_id = $role_row['role_id'];
}

$is_admin = ($role_id == $admin_role_id);

// Fetch breakdowns
// Admin sees all, Technicians see breakdowns they reported OR breakdowns for equipment they are assigned to?
// The user said "show all items that have been damaged", I'll default to showing all for now, or maybe restrict based on role if needed.
// For now, let's assume Admin sees all, Technician sees all (as they fix them).

$breakdowns = [];
$sql = "SELECT 
            b.*,
            e.equipment_name,
            e.equipment_image,
            e.serial_number,
            u.username as reported_by_username,
            u.firstname,
            u.lastname
        FROM breakdown b
        LEFT JOIN equipment e ON b.equipment_id = e.id
        LEFT JOIN users u ON b.reported_by_user_id = u.user_id
        ORDER BY b.breakdown_date DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $breakdowns[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Breakdown Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .breakdown-table {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .breakdown-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .breakdown-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }

        .breakdown-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            vertical-align: middle;
        }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-high {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-medium {
            background: #ffedd5;
            color: #9a3412;
        }

        .priority-low {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-open {
            background: #e0f2fe;
            color: #075985;
        }

        .status-fixed {
            background: #dcfce7;
            color: #166534;
        }

        .equipment-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .equipment-img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            background: #f3f4f6;
        }

        .empty-state {
            text-align: center;
            padding: 48px;
            color: #6b7280;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 24px;
            padding: 0;
            transition: transform 0.2s ease;
            color: #6b7280;
        }

        .toggle-btn:hover {
            transform: scale(1.1);
        }

        .toggle-btn.active {
            color: #10b981;
        }

        .toggle-btn.inactive {
            color: #ef4444;
        }

        .toggle-btn:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
    </style>
</head>

<body>
    <?php include './include/sidebar.php'; ?>

    <main class="main-content">
        <?php include './include/header.php'; ?>

        <div class="dashboard-content">
            <div class="page-header">
                <h2>Breakdown Reports</h2>
                <!-- Optional: Add Report Breakdown Button if needed here too -->
            </div>

            <div class="breakdown-table">
                <?php if (empty($breakdowns)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle fa-3x" style="color: #10b981; margin-bottom: 16px;"></i>
                        <h3>No Breakdowns Reported</h3>
                        <p>All equipment is running smoothly.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Equipment</th>
                                <th>Reported By</th>
                                <th>Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Issue</th>
                                <?php if ($is_admin): ?>
                                    <th>Change Status</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($breakdowns as $item): ?>
                                <tr>
                                    <td>#<?php echo $item['breakdown_id']; ?></td>
                                    <td>
                                        <div class="equipment-cell">
                                            <?php if ($item['equipment_image']): ?>
                                                <img src="../uploads/equipment/<?php echo htmlspecialchars($item['equipment_image']); ?>"
                                                    class="equipment-img" alt="Eq">
                                            <?php else: ?>
                                                <div class="equipment-img"
                                                    style="display:flex;align-items:center;justify-content:center;"><i
                                                        class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight:500;">
                                                    <?php echo htmlspecialchars($item['equipment_name']); ?></div>
                                                <div style="font-size:12px;color:#6b7280;">
                                                    <?php echo htmlspecialchars($item['serial_number']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['firstname'] . ' ' . $item['lastname']); ?>
                                        <div style="font-size:11px;color:#9ca3af;">
                                            <?php echo htmlspecialchars($item['reported_by_username']); ?></div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($item['breakdown_date'])); ?></td>
                                    <td>
                                        <?php
                                        $p = strtolower($item['priority']);
                                        $pClass = 'priority-low';
                                        if ($p == 'high')
                                            $pClass = 'priority-high';
                                        if ($p == 'medium')
                                            $pClass = 'priority-medium';
                                        ?>
                                        <span
                                            class="priority-badge <?php echo $pClass; ?>"><?php echo htmlspecialchars($item['priority']); ?></span>
                                    </td>
                                    <td>
                                        <span
                                            class="status-badge <?php echo ($item['statuss'] == 'Fixed' || $item['statuss'] == 'Resolved') ? 'status-fixed' : 'status-open'; ?>">
                                            <?php echo htmlspecialchars($item['statuss']); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 300px;">
                                        <?php echo htmlspecialchars(substr($item['issue_description'], 0, 100)) . (strlen($item['issue_description']) > 100 ? '...' : ''); ?>
                                    </td>
                                    <?php if ($is_admin): ?>
                                        <td>
                                            <div class="action-buttons">
                                                <?php
                                                $current_status = strtolower($item['statuss']);
                                                $is_active = ($current_status == 'active');
                                                ?>
                                                <button class="toggle-btn <?php echo $is_active ? 'active' : 'inactive'; ?>"
                                                    data-breakdown-id="<?php echo $item['breakdown_id']; ?>"
                                                    data-current-status="<?php echo htmlspecialchars($item['statuss']); ?>"
                                                    onclick="toggleStatus(this)" title="Toggle Status">
                                                    <?php if ($is_active): ?>
                                                        <i class="fas fa-toggle-on"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-toggle-off"></i>
                                                    <?php endif; ?>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="./assets/js/script.js"></script>
    <script>
        function toggleStatus(button) {
            const breakdownId = button.getAttribute('data-breakdown-id');
            const currentStatus = button.getAttribute('data-current-status');

            // Determine new status
            let newStatus = (currentStatus.toLowerCase() === 'active') ? 'Open' : 'active';

            // Disable button during request
            button.disabled = true;

            // Make AJAX request
            fetch('./update_breakdown_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    breakdown_id: breakdownId,
                    new_status: newStatus
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the button appearance
                        button.setAttribute('data-current-status', newStatus);

                        if (newStatus.toLowerCase() === 'active') {
                            button.classList.remove('inactive');
                            button.classList.add('active');
                            button.innerHTML = '<i class="fas fa-toggle-on"></i>';
                        } else {
                            button.classList.remove('active');
                            button.classList.add('inactive');
                            button.innerHTML = '<i class="fas fa-toggle-off"></i>';
                        }

                        // Show success message
                        alert('Status updated to: ' + newStatus);

                        // Reload the page to reflect changes in the status badge
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update status'));
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating status');
                    button.disabled = false;
                });
        }
    </script>
</body>

</html>