<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
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
   <?php include './includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <?php include '../includes/header.php'; ?>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <p class="welcome-text">Welcome back, Admin! Here's your equipment management system overview.</p>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon equipment">
                        <i class="fas fa-laptop-medical"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">Total Equipment</p>
                    </div>
                    <div class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i> 0%
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon maintenance">
                        <i class="fas fa-wrench"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">Active Maintenance</p>
                    </div>
                    <div class="stat-trend warning-trend">
                        <i class="fas fa-clock"></i> Pending
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon breakdown">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">Active Breakdowns</p>
                    </div>
                    <div class="stat-trend danger">
                        <i class="fas fa-exclamation-circle"></i> Critical
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">System Users</p>
                    </div>
                    <div class="stat-trend positive">
                        <i class="fas fa-user-check"></i> Active
                    </div>
                </div>
            </div>

            <!-- Additional Stats Row -->
            <div class="stats-grid secondary-stats">
                <div class="stat-card small">
                    <div class="stat-icon-small categories">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">Categories</p>
                    </div>
                </div>

                <div class="stat-card small">
                    <div class="stat-icon-small locations">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">Locations</p>
                    </div>
                </div>

                <div class="stat-card small">
                    <div class="stat-icon-small schedules">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">Schedules</p>
                    </div>
                </div>

                <div class="stat-card small">
                    <div class="stat-icon-small completed">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-value">0</h3>
                        <p class="stat-label">Completed Today</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Chart Section -->
                <div class="card chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Maintenance Overview</h3>
                        <div class="card-actions">
                            <select class="chart-filter">
                                <option>Last 7 Days</option>
                                <option>Last 30 Days</option>
                                <option>Last 6 Months</option>
                                <option>This Year</option>
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
                            <div class="legend-item">
                                <span class="legend-dot operational"></span>
                                <span class="legend-text">Operational</span>
                                <span class="legend-value">0</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot maintenance-dot"></span>
                                <span class="legend-text">Under Maintenance</span>
                                <span class="legend-value">0</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot broken"></span>
                                <span class="legend-text">Broken Down</span>
                                <span class="legend-value">0</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot inactive"></span>
                                <span class="legend-text">Inactive</span>
                                <span class="legend-value">0</span>
                            </div>
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
                        <a href="#" class="view-all">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon maintenance">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Maintenance completed for <strong>Medical Scanner</strong></p>
                                    <span class="activity-time">2 hours ago</span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon breakdown">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Breakdown reported: <strong>X-Ray Machine</strong> - High Priority</p>
                                    <span class="activity-time">4 hours ago</span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon equipment">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">New equipment added to inventory</p>
                                    <span class="activity-time">6 hours ago</span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon user">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">New user registered: <strong>John Technician</strong></p>
                                    <span class="activity-time">1 day ago</span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon schedule">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Maintenance schedule created for 5 equipment</p>
                                    <span class="activity-time">2 days ago</span>
                                </div>
                            </div>
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
                            <div class="alert-item high">
                                <div class="alert-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="alert-content">
                                    <h4>High Priority Breakdown</h4>
                                    <p>Equipment requires immediate attention</p>
                                    <span class="alert-time">30 mins ago</span>
                                </div>
                            </div>
                            <div class="alert-item medium">
                                <div class="alert-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="alert-content">
                                    <h4>Maintenance Overdue</h4>
                                    <p>3 equipment past scheduled maintenance</p>
                                    <span class="alert-time">2 hours ago</span>
                                </div>
                            </div>
                            <div class="alert-item low">
                                <div class="alert-icon">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <div class="alert-content">
                                    <h4>Warranty Expiring</h4>
                                    <p>2 equipment warranties expire this month</p>
                                    <span class="alert-time">5 hours ago</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & System Status -->
            <div class="content-grid bottom-grid">
                <!-- Quick Actions -->
                <div class="card quick-actions-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <button class="action-btn">
                                <i class="fas fa-plus-circle"></i>
                                <span>Add Equipment</span>
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-wrench"></i>
                                <span>Schedule Maintenance</span>
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Report Breakdown</span>
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-user-plus"></i>
                                <span>Add User</span>
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-layer-group"></i>
                                <span>Add Category</span>
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Add Location</span>
                            </button>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="./assets/js/script.js"></script>
</body>
</html>
