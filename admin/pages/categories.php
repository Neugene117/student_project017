<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Include database connection
include('../../config/db.php');

// Generate CSRF token if not exists
if (empty($_SESSION['category_token'])) {
    $_SESSION['category_token'] = bin2hex(random_bytes(32));
}

// Check if redirect came from successful insert
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Category added successfully!";
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['category_token']) {
        $error_message = "Security token validation failed. Please try again.";
    } else {
        $category_name = trim($_POST['category_name']);
        
        if (!empty($category_name)) {
            $user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not set
            $sql = "INSERT INTO category (category_name, user_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $category_name, $user_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                // Regenerate token after successful submission
                $_SESSION['category_token'] = bin2hex(random_bytes(32));
                // Redirect to prevent duplicate submission on refresh
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $error_message = "Error adding category: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Category name cannot be empty!";
        }
    }
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $category_id = intval($_POST['category_id']);
    
    $sql = "DELETE FROM category WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        $success_message = "Category deleted successfully!";
    } else {
        $error_message = "Error deleting category: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all categories
$sql = "SELECT * FROM category ORDER BY created_at DESC";
$result = $conn->query($sql);
$categories = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .category-form {
            background: var(--white);
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 13px;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .categories-table {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .categories-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .categories-table thead {
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }

        .categories-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 14px;
        }

        .categories-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
        }

        .categories-table tbody tr:hover {
            background: var(--gray-50);
        }

        .categories-table tbody tr:last-child td {
            border-bottom: none;
        }

        .category-id {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .category-actions {
            display: flex;
            gap: 8px;
        }

        .created-date {
            font-size: 13px;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: flex-end;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .category-actions {
                flex-direction: column;
            }

            .btn-edit, .btn-danger {
                width: 100%;
            }
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-tools"></i>
                <span class="logo-text">EMS Admin</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../dashboard.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-laptop-medical"></i>
                        <span class="nav-text">Equipment</span>
                        <span class="badge">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-wrench"></i>
                        <span class="nav-text">Maintenance</span>
                        <span class="badge warning">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Schedules</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="nav-text">Breakdowns</span>
                        <span class="badge danger">0</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="categories.php" class="nav-link">
                        <i class="fas fa-layer-group"></i>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="nav-text">Locations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-user-shield"></i>
                        <span class="nav-text">Roles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="toggle-btn" id="toggleBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Categories</h1>
            </div>

            <div class="header-right">
                <!-- Notifications -->
                <div class="notification-wrapper">
                    <button class="notification-btn" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count">5</span>
                    </button>

                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="dropdown-header">
                            <h3>Notifications</h3>
                            <button class="mark-read">Mark all as read</button>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item unread">
                                <div class="notification-icon breakdown">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text"><strong>High Priority:</strong> Equipment breakdown reported</p>
                                    <span class="notification-time">30 minutes ago</span>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-footer">
                            <a href="#">View all notifications</a>
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <div class="profile-wrapper">
                    <button class="profile-btn" id="profileBtn">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=2563eb&color=fff" alt="Profile" class="profile-img">
                        <span class="profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>

                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-header">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=2563eb&color=fff" alt="Profile">
                            <div class="profile-info">
                                <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                                <p>User Account</p>
                            </div>
                        </div>
                        <ul class="profile-menu">
                            <li>
                                <a href="#">
                                    <i class="fas fa-user"></i>
                                    <span>My Profile</span>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fas fa-question-circle"></i>
                                    <span>Help & Support</span>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="../logout.php" class="logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="dashboard-content">
            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Mini -->
            <div class="stats-mini">
                <div class="stat-mini-card">
                    <div class="stat-mini-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h3><?php echo count($categories); ?></h3>
                        <p>Total Categories</p>
                    </div>
                </div>
            </div>

            <!-- Add Category Form -->
            <div class="category-form">
                <h3 style="margin-top: 0; color: var(--gray-800); font-size: 18px; margin-bottom: 20px;">
                    <i class="fas fa-plus-circle" style="color: var(--primary-blue); margin-right: 8px;"></i>
                    Add New Category
                </h3>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="category_name">Category Name</label>
                        <input 
                            type="text" 
                            id="category_name" 
                            name="category_name" 
                            placeholder="Enter category name (e.g., Medical Equipment, Tools, etc.)"
                            required
                        >
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['category_token']); ?>">
                </form>
            </div>

            <!-- Categories Table -->
            <div class="categories-table">
                <?php if (count($categories) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td class="category-id">#<?php echo htmlspecialchars($category['category_id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td>
                                        <span class="created-date">
                                            <?php echo date('M d, Y H:i', strtotime($category['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                            <button type="submit" class="btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Categories Yet</h3>
                        <p>Start by adding your first equipment category above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
</body>
</html>
