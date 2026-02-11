<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Include database connection
require_once '../config/db.php';

// Get user role from session
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get equipment ID from URL
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($equipment_id <= 0) {
    header("Location: equipment.php?error=" . urlencode("Invalid equipment ID"));
    exit();
}

// Fetch equipment details
$equipment = null;
$equipment_query = "SELECT e.id, e.equipment_name, e.equipment_image, e.serial_number, e.statuss, 
                    e.purchase_date, e.starting_date, e.expired_date, e.created_at, e.updated_at,
                    c.category_name, l.location_name
                    FROM equipment e
                    LEFT JOIN category c ON e.category_id = c.category_id
                    LEFT JOIN equipment_location l ON e.equipment_location_id = l.location_id
                    WHERE e.id = ?";

$stmt = mysqli_prepare($conn, $equipment_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $equipment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $equipment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// If equipment not found, redirect
if (!$equipment) {
    header("Location: equipment.php?error=" . urlencode("Equipment not found"));
    exit();
}

// Fetch maintenance records for this equipment
$maintenance_records = [];
$maintenance_query = "SELECT m.mid, m.maintenance_type, m.completed_date, m.description, 
                     m.cost, m.statuss, m.created_at, u.username
                     FROM maintenance m
                     LEFT JOIN users u ON m.user_id = u.user_id
                     WHERE m.equipment_id = ?
                     ORDER BY m.completed_date DESC, m.created_at DESC";

$stmt = mysqli_prepare($conn, $maintenance_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $equipment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $maintenance_records[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Fetch maintenance schedules for this equipment
$maintenance_schedules = [];
$schedule_query = "SELECT ms.schedule_id, ms.maintenance_type, ms.interval_value, ms.interval_unit,
                  ms.start_date, u.username
                  FROM maintenance_schedule ms
                  LEFT JOIN users u ON ms.assigned_to_user_id = u.user_id
                  WHERE ms.equipment_id = ?
                  ORDER BY ms.start_date DESC";

$stmt = mysqli_prepare($conn, $schedule_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $equipment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $maintenance_schedules[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($equipment['equipment_name']); ?> - Equipment Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .equipment-details-container {
            padding: 30px;
            background: #f8fafc;
        }

        .details-header {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-bottom: 40px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .details-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            border-radius: 12px;
            overflow: hidden;
        }

        .details-image img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }

        .details-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: fit-content;
        }

        .status-active {
            background: #ecfdf5;
            color: #047857;
        }

        .status-inactive {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-maintenance {
            background: #fef3c7;
            color: #b45309;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #64748b;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .badge-small {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-completed {
            background: #ecfdf5;
            color: #047857;
        }

        .badge-pending {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-failed {
            background: #fef2f2;
            color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #740101;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: background 0.3s;
        }

        .back-button:hover {
            background: #be4747;
        }

        .warranty-info {
            background: #f0f9ff;
            border-left: 4px solid #0284c7;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .warranty-info h4 {
            margin: 0 0 8px 0;
            color: #0c4a6e;
        }

        .warranty-info p {
            margin: 4px 0;
            font-size: 14px;
            color: #0c4a6e;
        }

        @media (max-width: 1024px) {
            .details-header {
                grid-template-columns: 1fr;
            }

            .details-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .equipment-details-container {
                padding: 15px;
            }

            .section {
                padding: 20px;
            }

            .details-header {
                padding: 20px;
                gap: 20px;
            }

            th, td {
                padding: 10px;
                font-size: 12px;
            }

            .section-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <?php include './include/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <?php include './include/header.php'; ?>

        <!-- Equipment Details Content -->
        <div class="dashboard-content">
            <a href="equipment.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Equipment
            </a>

            <!-- Equipment Header with Image and Basic Info -->
            <div class="details-header">
                <div class="details-image">
                    <?php
                        $img_src = (!empty($equipment['equipment_image']) && file_exists('../uploads/equipment/' . $equipment['equipment_image']))
                            ? '../uploads/equipment/' . htmlspecialchars($equipment['equipment_image'])
                            : '../static/images/default-equipment.png';
                    ?>
                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($equipment['equipment_name']); ?>">
                </div>

                <div class="details-info">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="info-label">Equipment Name</div>
                        <h2 style="margin: 0; color: #1e293b; font-size: 28px;">
                            <?php echo htmlspecialchars($equipment['equipment_name']); ?>
                        </h2>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Serial Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($equipment['serial_number'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Category</div>
                        <div class="info-value"><?php echo htmlspecialchars($equipment['category_name'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($equipment['location_name'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Purchase Date</div>
                        <div class="info-value">
                            <?php echo !empty($equipment['purchase_date']) ? date('M d, Y', strtotime($equipment['purchase_date'])) : 'N/A'; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <?php
                            $status_class = 'status-' . strtolower($equipment['statuss']);
                            if (!in_array($equipment['statuss'], ['Active', 'Inactive', 'Maintenance'])) {
                                $status_class = 'status-active';
                            }
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($equipment['statuss']); ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Added On</div>
                        <div class="info-value">
                            <?php echo !empty($equipment['created_at']) ? date('M d, Y', strtotime($equipment['created_at'])) : 'N/A'; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value">
                            <?php echo !empty($equipment['updated_at']) ? date('M d, Y', strtotime($equipment['updated_at'])) : 'N/A'; ?>
                        </div>
                    </div>

                    <!-- Warranty Information -->
                    <?php if (!empty($equipment['starting_date']) || !empty($equipment['expired_date'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="warranty-info">
                            <h4><i class="fas fa-shield-alt"></i> Warranty Information</h4>
                            <p><strong>Start Date:</strong> <?php echo !empty($equipment['starting_date']) ? date('M d, Y', strtotime($equipment['starting_date'])) : 'N/A'; ?></p>
                            <p><strong>Expiration Date:</strong> <?php echo !empty($equipment['expired_date']) ? date('M d, Y', strtotime($equipment['expired_date'])) : 'N/A'; ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Maintenance Records Section -->
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Maintenance Records
                </h3>

                <?php if (count($maintenance_records) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Completed Date</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Description</th>
                                <th>Performed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenance_records as $record): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($record['maintenance_type']); ?></strong></td>
                                <td><?php echo !empty($record['completed_date']) ? date('M d, Y', strtotime($record['completed_date'])) : 'Pending'; ?></td>
                                <td>
                                    <?php
                                        $status_map = [
                                            'Completed' => 'badge-completed',
                                            'Pending' => 'badge-pending',
                                            'Failed' => 'badge-failed'
                                        ];
                                        $status_class = $status_map[$record['statuss']] ?? 'badge-pending';
                                    ?>
                                    <span class="badge-small <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($record['statuss']); ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($record['cost']) ?  number_format($record['cost'], 2) : 'N/A'; ?> RWF</td>
                                <td><?php echo htmlspecialchars($record['description'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['username'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No maintenance records found for this equipment.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Maintenance Schedules Section -->
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-calendar-alt"></i> Maintenance Schedules
                </h3>

                <?php if (count($maintenance_schedules) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Maintenance Type</th>
                                <th>Interval</th>
                                <th>Start Date</th>
                                <th>Assigned To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenance_schedules as $schedule): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($schedule['maintenance_type']); ?></strong></td>
                                <td>
                                    Every <?php echo htmlspecialchars($schedule['interval_value']); ?> 
                                    <?php echo htmlspecialchars(strtolower($schedule['interval_unit'])); ?>
                                    (s)
                                </td>
                                <td><?php echo !empty($schedule['start_date']) ? date('M d, Y', strtotime($schedule['start_date'])) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($schedule['username'] ?? 'Unassigned'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No maintenance schedules found for this equipment.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="./assets/js/script.js"></script>
</body>
</html>
