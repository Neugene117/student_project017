<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
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

// Set default date range
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'daily';
$filter_type = $_GET['filter_type'] ?? 'all'; // all, maintenance, breakdown, equipment

// Determine active tab
$active_tab = $_GET['active_tab'] ?? 'maintenance-tab';
$valid_tabs = ['maintenance-tab', 'breakdown-tab', 'trend-tab'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'maintenance-tab';
}

// Validate dates
$start_date = date('Y-m-d', strtotime($start_date));
$end_date = date('Y-m-d', strtotime($end_date));

// Function to get maintenance statistics
function getMaintenanceStats($conn, $start_date, $end_date) {
    $sql = "SELECT 
                COUNT(*) as total_maintenance,
                SUM(CASE WHEN statuss = 'Completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN statuss = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN statuss = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(cost) as total_cost,
                AVG(cost) as avg_cost
            FROM maintenance 
            WHERE DATE(completed_date) BETWEEN ? AND ?
            OR (statuss = 'Scheduled' AND DATE(created_at) BETWEEN ? AND ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    return $stats;
}

// Function to get breakdown statistics
function getBreakdownStats($conn, $start_date, $end_date) {
    $sql = "SELECT 
                COUNT(*) as total_breakdowns,
                SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) as `high_priority`,
                SUM(CASE WHEN priority = 'Medium' THEN 1 ELSE 0 END) as `medium_priority`,
                SUM(CASE WHEN priority = 'Low' THEN 1 ELSE 0 END) as `low_priority`,
                SUM(CASE WHEN statuss = 'Resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN statuss = 'Open' THEN 1 ELSE 0 END) as open_status,
                SUM(CASE WHEN statuss = 'Under Repair' THEN 1 ELSE 0 END) as under_repair
            FROM breakdown 
            WHERE DATE(breakdown_date) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    return $stats;
}

// Equipment stats function removed

// Function to get daily maintenance report
function getDailyMaintenanceReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
                DATE(completed_date) as report_date,
                COUNT(*) as total_count,
                SUM(CASE WHEN statuss = 'Completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(cost) as daily_cost
            FROM maintenance 
            WHERE DATE(completed_date) BETWEEN ? AND ?
            GROUP BY DATE(completed_date)
            ORDER BY report_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

// Function to get monthly maintenance report
function getMonthlyMaintenanceReport($conn, $year) {
    $sql = "SELECT 
                MONTH(completed_date) as month_num,
                MONTHNAME(completed_date) as month_name,
                COUNT(*) as total_count,
                SUM(CASE WHEN statuss = 'Completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(cost) as monthly_cost
            FROM maintenance 
            WHERE YEAR(completed_date) = ?
            GROUP BY MONTH(completed_date), MONTHNAME(completed_date)
            ORDER BY month_num DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

// Function to get daily breakdown report
function getDailyBreakdownReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
                DATE(breakdown_date) as report_date,
                COUNT(*) as total_count,
                SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) as high_priority_count,
                SUM(CASE WHEN statuss = 'Resolved' THEN 1 ELSE 0 END) as resolved_count
            FROM breakdown 
            WHERE DATE(breakdown_date) BETWEEN ? AND ?
            GROUP BY DATE(breakdown_date)
            ORDER BY report_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

// Function to get detailed maintenance records
function getMaintenanceRecords($conn, $start_date, $end_date) {
    $sql = "SELECT 
                m.mid,
                m.maintenance_type,
                m.completed_date,
                m.description,
                m.cost,
                m.statuss,
                e.equipment_name,
                u.firstname,
                u.lastname
            FROM maintenance m
            LEFT JOIN equipment e ON m.equipment_id = e.id
            LEFT JOIN users u ON m.user_id = u.user_id
            WHERE DATE(m.completed_date) BETWEEN ? AND ?
            OR (m.statuss = 'Scheduled' AND DATE(m.created_at) BETWEEN ? AND ?)
            ORDER BY m.completed_date DESC
            LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

// Function to get detailed breakdown records
function getBreakdownRecords($conn, $start_date, $end_date) {
    $sql = "SELECT 
                b.breakdown_id,
                b.breakdown_date,
                b.issue_description,
                b.priority,
                b.statuss,
                e.equipment_name,
                u.firstname,
                u.lastname
            FROM breakdown b
            LEFT JOIN equipment e ON b.equipment_id = e.id
            LEFT JOIN users u ON b.reported_by_user_id = u.user_id
            WHERE DATE(b.breakdown_date) BETWEEN ? AND ?
            ORDER BY b.breakdown_date DESC
            LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

// Get statistics based on filter
$maintenance_stats = getMaintenanceStats($conn, $start_date, $end_date);
$breakdown_stats = getBreakdownStats($conn, $start_date, $end_date);

// Get report data based on type
$report_data = [];
if ($report_type === 'daily') {
    if ($filter_type === 'maintenance' || $filter_type === 'all') {
        $report_data['maintenance'] = getDailyMaintenanceReport($conn, $start_date, $end_date);
    }
    if ($filter_type === 'breakdown' || $filter_type === 'all') {
        $report_data['breakdown'] = getDailyBreakdownReport($conn, $start_date, $end_date);
    }
} elseif ($report_type === 'yearly') {
    $year = date('Y', strtotime($start_date));
    if ($filter_type === 'maintenance' || $filter_type === 'all') {
        $report_data['maintenance'] = getMonthlyMaintenanceReport($conn, $year);
    }
}

// Get detailed records
$maintenance_records = [];
$breakdown_records = [];
if ($filter_type === 'maintenance' || $filter_type === 'all') {
    $maintenance_records = getMaintenanceRecords($conn, $start_date, $end_date);
}
if ($filter_type === 'breakdown' || $filter_type === 'all') {
    $breakdown_records = getBreakdownRecords($conn, $start_date, $end_date);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <link rel="stylesheet" href="./assets/css/report.css">
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
                <h1>Reports - Equipment Management System</h1>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <i class="fas fa-filter"></i>
                    <h2>Report Filters</h2>
                </div>

                <form method="GET" action="">
                    <input type="hidden" id="active_tab" name="active_tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="start_date">
                                <i class="far fa-calendar"></i> Start Date
                            </label>
                            <input 
                                type="date" 
                                id="start_date" 
                                name="start_date" 
                                value="<?php echo htmlspecialchars($start_date); ?>"
                                required
                            >
                        </div>

                        <div class="filter-group">
                            <label for="end_date">
                                <i class="far fa-calendar"></i> End Date
                            </label>
                            <input 
                                type="date" 
                                id="end_date" 
                                name="end_date" 
                                value="<?php echo htmlspecialchars($end_date); ?>"
                                required
                            >
                        </div>

                        <div class="filter-group">
                            <label for="report_type">
                                <i class="fas fa-chart-line"></i> Report Type
                            </label>
                            <select id="report_type" name="report_type">
                                <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily Report</option>
                                <option value="yearly" <?php echo $report_type === 'yearly' ? 'selected' : ''; ?>>Monthly/Yearly Report</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter_type">
                                <i class="fas fa-layer-group"></i> Category
                            </label>
                            <select id="filter_type" name="filter_type">
                                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Reports</option>
                                <option value="maintenance" <?php echo $filter_type === 'maintenance' ? 'selected' : ''; ?>>Maintenance Only</option>
                                <option value="breakdown" <?php echo $filter_type === 'breakdown' ? 'selected' : ''; ?>>Breakdowns Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                        <button type="button" class="btn-reset" onclick="window.location.href='report.php'">
                            <i class="fas fa-redo"></i> Reset Filters
                        </button>
                        <button type="button" class="btn-export" onclick="window.print()">
                            <i class="fas fa-file-pdf"></i> Export to PDF
                        </button>
                    </div>
                </form>
            </div>




            <!-- Tab Navigation -->
            <div class="tab-container">
                <button class="tab-btn <?php echo $active_tab === 'maintenance-tab' ? 'active' : ''; ?>" onclick="openTab('maintenance-tab')">Maintenance Activities Report</button>
                <button class="tab-btn <?php echo $active_tab === 'breakdown-tab' ? 'active' : ''; ?>" onclick="openTab('breakdown-tab')">Equipment Breakdown Report</button>
                <button class="tab-btn <?php echo $active_tab === 'trend-tab' ? 'active' : ''; ?>" onclick="openTab('trend-tab')">Daily Maintenance Trend</button>
            </div>

            <!-- Maintenance Report Tab -->
            <div id="maintenance-tab" class="tab-content <?php echo $active_tab === 'maintenance-tab' ? 'active' : ''; ?>">
                <?php if (($filter_type === 'all' || $filter_type === 'maintenance') && !empty($maintenance_records)): ?>
                <div class="report-section">
                    <div class="report-header">
                        <h3>
                            <i class="fas fa-wrench"></i>
                            Maintenance Activities Report
                        </h3>
                        <span class="badge badge-info">
                            <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
                        </span>
                    </div>

                    <div class="report-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Equipment</th>
                                    <th>Type</th>
                                    <th>Completed Date</th>
                                    <th>Technician</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_records as $record): ?>
                                <tr>
                                    <td class="category-id">#<?php echo $record['mid']; ?></td>
                                    <td><?php echo htmlspecialchars($record['equipment_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($record['maintenance_type']); ?></td>
                                    <td><?php echo $record['completed_date'] ? date('M d, Y H:i', strtotime($record['completed_date'])) : 'Not completed'; ?></td>
                                    <td><?php echo htmlspecialchars($record['firstname'] . ' ' . $record['lastname']); ?></td>
                                    <td>$<?php echo number_format($record['cost'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = 'secondary';
                                        if ($record['statuss'] === 'Completed') $status_class = 'success';
                                        elseif ($record['statuss'] === 'In Progress') $status_class = 'warning';
                                        elseif ($record['statuss'] === 'Scheduled') $status_class = 'info';
                                        ?>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($record['statuss']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="report-section">
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <h3>No Maintenance Records</h3>
                        <p>No maintenance activities found for the selected period.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Breakdown Report Tab -->
            <div id="breakdown-tab" class="tab-content <?php echo $active_tab === 'breakdown-tab' ? 'active' : ''; ?>">
                <?php if (($filter_type === 'all' || $filter_type === 'breakdown') && !empty($breakdown_records)): ?>
                <div class="report-section">
                    <div class="report-header">
                        <h3>
                            <i class="fas fa-exclamation-triangle"></i>
                            Equipment Breakdown Report
                        </h3>
                        <span class="badge badge-info">
                            <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
                        </span>
                    </div>

                    <div class="report-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Equipment</th>
                                    <th>Issue Description</th>
                                    <th>Breakdown Date</th>
                                    <th>Reported By</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($breakdown_records as $record): ?>
                                <tr>
                                    <td class="category-id">#<?php echo $record['breakdown_id']; ?></td>
                                    <td><?php echo htmlspecialchars($record['equipment_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($record['issue_description'], 0, 50)) . (strlen($record['issue_description']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($record['breakdown_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['firstname'] . ' ' . $record['lastname']); ?></td>
                                    <td>
                                        <?php 
                                        $priority_class = 'secondary';
                                        if ($record['priority'] === 'High') $priority_class = 'danger';
                                        elseif ($record['priority'] === 'Medium') $priority_class = 'warning';
                                        elseif ($record['priority'] === 'Low') $priority_class = 'info';
                                        ?>
                                        <span class="badge badge-<?php echo $priority_class; ?>">
                                            <?php echo htmlspecialchars($record['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'secondary';
                                        if ($record['statuss'] === 'Resolved') $status_class = 'success';
                                        elseif ($record['statuss'] === 'Under Repair') $status_class = 'warning';
                                        elseif ($record['statuss'] === 'Open') $status_class = 'danger';
                                        ?>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($record['statuss']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="report-section">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>No Breakdown Records</h3>
                        <p>No equipment breakdowns reported for the selected period.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Trend Tab -->
            <div id="trend-tab" class="tab-content <?php echo $active_tab === 'trend-tab' ? 'active' : ''; ?>">
                <?php if ($report_type === 'daily' && isset($report_data['maintenance']) && !empty($report_data['maintenance'])): ?>
                <div class="report-section">
                    <div class="report-header">
                        <h3>
                            <i class="fas fa-chart-bar"></i>
                            Daily Maintenance Trend
                        </h3>
                    </div>

                    <div class="report-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Total Tasks</th>
                                    <th>Completed</th>
                                    <th>Daily Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['maintenance'] as $row): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['report_date'])); ?></td>
                                    <td><?php echo $row['total_count']; ?></td>
                                    <td><span class="badge badge-success"><?php echo $row['completed_count']; ?></span></td>
                                    <td>$<?php echo number_format($row['daily_cost'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($report_type === 'yearly' && isset($report_data['maintenance']) && !empty($report_data['maintenance'])): ?>
                <div class="report-section">
                    <div class="report-header">
                        <h3>
                            <i class="fas fa-chart-line"></i>
                            Monthly Maintenance Summary
                        </h3>
                    </div>

                    <div class="report-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Total Tasks</th>
                                    <th>Completed</th>
                                    <th>Monthly Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['maintenance'] as $row): ?>
                                <tr>
                                    <td><?php echo $row['month_name']; ?></td>
                                    <td><?php echo $row['total_count']; ?></td>
                                    <td><span class="badge badge-success"><?php echo $row['completed_count']; ?></span></td>
                                    <td>$<?php echo number_format($row['monthly_cost'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="report-section">
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h3>No Trend Data</h3>
                        <p>No trend data available for the selected report type.</p>
                    </div>
                </div>
                <?php endif; ?>
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
    <script>
        function openTab(tabId) {
            // Update hidden input
            const activeTabInput = document.getElementById('active_tab');
            if (activeTabInput) {
                activeTabInput.value = tabId;
            }

            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));

            // Deactivate all tab buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(button => button.classList.remove('active'));

            // Show specific tab content
            document.getElementById(tabId).classList.add('active');

            // Activate specific tab button
             const btnMap = {
                'maintenance-tab': 0,
                'breakdown-tab': 1,
                'trend-tab': 2
            };
            if (buttons[btnMap[tabId]]) {
                buttons[btnMap[tabId]].classList.add('active');
            }
        }
    </script>
</body>
</html>