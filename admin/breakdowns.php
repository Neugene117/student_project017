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

        .priority-high { background: #fee2e2; color: #991b1b; }
        .priority-medium { background: #ffedd5; color: #9a3412; }
        .priority-low { background: #d1fae5; color: #065f46; }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-open { background: #e0f2fe; color: #075985; }
        .status-fixed { background: #dcfce7; color: #166534; }
        
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($breakdowns as $item): ?>
                                <tr>
                                    <td>#<?php echo $item['breakdown_id']; ?></td>
                                    <td>
                                        <div class="equipment-cell">
                                            <?php if ($item['equipment_image']): ?>
                                                <img src="../uploads/equipment/<?php echo htmlspecialchars($item['equipment_image']); ?>" class="equipment-img" alt="Eq">
                                            <?php else: ?>
                                                <div class="equipment-img" style="display:flex;align-items:center;justify-content:center;"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight:500;"><?php echo htmlspecialchars($item['equipment_name']); ?></div>
                                                <div style="font-size:12px;color:#6b7280;"><?php echo htmlspecialchars($item['serial_number']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['firstname'] . ' ' . $item['lastname']); ?>
                                        <div style="font-size:11px;color:#9ca3af;"><?php echo htmlspecialchars($item['reported_by_username']); ?></div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($item['breakdown_date'])); ?></td>
                                    <td>
                                        <?php 
                                            $p = strtolower($item['priority']);
                                            $pClass = 'priority-low';
                                            if ($p == 'high') $pClass = 'priority-high';
                                            if ($p == 'medium') $pClass = 'priority-medium';
                                        ?>
                                        <span class="priority-badge <?php echo $pClass; ?>"><?php echo htmlspecialchars($item['priority']); ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo ($item['statuss'] == 'Fixed' || $item['statuss'] == 'Resolved') ? 'status-fixed' : 'status-open'; ?>">
                                            <?php echo htmlspecialchars($item['statuss']); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 300px;">
                                        <?php echo htmlspecialchars(substr($item['issue_description'], 0, 100)) . (strlen($item['issue_description']) > 100 ? '...' : ''); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="./assets/js/script.js"></script>
</body>
</html>