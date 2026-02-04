<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Include database connection
include('../config/db.php');

// Check if user has admin role
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: ./dashboard.php?error=" . urlencode("User information not found"));
    exit();
}

// Fetch user's role and name
$sql_role = "SELECT r.role_name, u.firstname, u.lastname FROM users u 
             JOIN role r ON u.role_id = r.role_id 
             WHERE u.user_id = ?";
$stmt_role = $conn->prepare($sql_role);
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$result_role = $stmt_role->get_result();

if ($result_role->num_rows === 0) {
    header("Location: ./dashboard.php?error=" . urlencode("User role not found"));
    exit();
}

$user_data = $result_role->fetch_assoc();
$user_role = $user_data['role_name'];
$user_fullname = $user_data['firstname'] . ' ' . $user_data['lastname'];
$stmt_role->close();

// Function to get role statistics
function getRoleStats($conn) {
    $sql = "SELECT 
                r.role_id, 
                r.role_name, 
                COUNT(u.user_id) as user_count 
            FROM role r 
            LEFT JOIN users u ON r.role_id = u.role_id 
            GROUP BY r.role_id, r.role_name 
            ORDER BY r.role_name";
    
    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

$role_stats = getRoleStats($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Reports - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <link rel="stylesheet" href="./assets/css/role.css">
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
            
            <!-- Print Header -->
            <div class="print-header">
                <img src="../static/images/logo.JPG" alt="Company Logo">
                <h1>Role Reports - Equipment Management System</h1>
            </div>

            <!-- Filter Section (Simplified for Actions) -->
            <div class="filter-section">
                <div class="filter-header">
                    <i class="fas fa-users-cog"></i>
                    <h2>Role Report</h2>
                    <div style="flex-grow: 1;"></div>
                    <div class="filter-actions">
                        <button type="button" class="btn-export" onclick="window.print()">
                            <i class="fas fa-file-pdf"></i> Export to PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Role Report Table -->
            <div class="report-section">
                <div class="report-header">
                    <h3>
                        <i class="fas fa-list-ul"></i>
                        All Roles Overview
                    </h3>
                    <span class="badge badge-info">
                        As of <?php echo date('M d, Y'); ?>
                    </span>
                </div>

                <div class="report-table">
                    <?php if (!empty($role_stats)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Role ID</th>
                                <th>Role Name</th>
                                <th>Number of Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($role_stats as $role): ?>
                            <tr>
                                <td>#<?php echo $role['role_id']; ?></td>
                                <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo $role['user_count']; ?> Users
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Roles Found</h3>
                        <p>No roles available in the system.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Print Footer / Signatures -->
            <div class="print-footer">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                    <div class="signature-title">Prepared By (<?php echo htmlspecialchars($user_role); ?>)</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-name">______________________</div>
                    <div class="signature-title">Director Signature</div>
                </div>
            </div>

        </div>
    </main>

    <script src="./assets/js/script.js"></script>
</body>
</html>