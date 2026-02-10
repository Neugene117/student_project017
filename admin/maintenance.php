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

// Fetch equipment based on role
$equipment_list = [];

if ($role_id == 1) {
    // Admin: Fetch all equipment
    $sql = "SELECT e.*, c.category_name, l.location_name 
            FROM equipment e 
            LEFT JOIN category c ON e.category_id = c.category_id 
            LEFT JOIN equipment_location l ON e.equipment_location_id = l.location_id
            ORDER BY e.created_at DESC";
    $stmt = $conn->prepare($sql);
} else {
    // Non-Admin: Fetch assigned equipment
    // Assigned via maintenance schedule
    $sql = "SELECT DISTINCT e.*, c.category_name, l.location_name 
            FROM equipment e 
            LEFT JOIN category c ON e.category_id = c.category_id 
            LEFT JOIN equipment_location l ON e.equipment_location_id = l.location_id
            LEFT JOIN maintenance_schedule ms ON e.id = ms.equipment_id
            WHERE ms.assigned_to_user_id = ?
            ORDER BY e.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $equipment_list[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Equipment List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            padding: 24px 0;
        }

        .equipment-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .equipment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }

        .card-image {
            height: 200px;
            background-color: #f3f4f6;
            position: relative;
            overflow: hidden;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #9ca3af;
            font-size: 3rem;
        }

        .card-content {
            padding: 20px;
            flex: 1;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .card-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .card-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { background: #d1fae5; color: #065f46; }
        .status-maintenance { background: #fef3c7; color: #92400e; }
        .status-inactive { background: #f3f4f6; color: #374151; }
        
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
                <h2>Equipment Maintenance Overview</h2>
            </div>

            <?php if (empty($equipment_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-tools fa-3x" style="margin-bottom: 16px;"></i>
                    <h3>No equipment found</h3>
                    <p>There is no equipment assigned to you at the moment.</p>
                </div>
            <?php else: ?>
                <div class="equipment-grid">
                    <?php foreach ($equipment_list as $item): ?>
                        <a href="equipment-details.php?id=<?php echo $item['id']; ?>" class="equipment-card">
                            <div class="card-image">
                                <?php if (!empty($item['equipment_image'])): ?>
                                    <img src="../uploads/equipment/<?php echo htmlspecialchars($item['equipment_image']); ?>" alt="<?php echo htmlspecialchars($item['equipment_name']); ?>">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-content">
                                <div class="card-title"><?php echo htmlspecialchars($item['equipment_name']); ?></div>
                                <div class="card-meta">
                                    <span class="meta-item">
                                        <i class="fas fa-layer-group"></i>
                                        <?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($item['location_name'] ?? 'Unknown'); ?>
                                    </span>
                                </div>
                                <?php
                                    $statusClass = 'status-inactive';
                                    $statusText = $item['statuss'] ?? 'Unknown';
                                    if (stripos($statusText, 'active') !== false) $statusClass = 'status-active';
                                    if (stripos($statusText, 'maintenance') !== false) $statusClass = 'status-maintenance';
                                ?>
                                <span class="card-status <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($statusText); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="./assets/js/script.js"></script>
</body>
</html>