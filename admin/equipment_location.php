<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}
// Check if user has admin role
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
$is_admin = ($user_role === 'admin');

// If user is not admin, redirect them
if (!$is_admin) {
    header("Location: ./dashboard.php?error=" . urlencode("You do not have permission to access this page"));
    exit();
}
// Include database connection
include('../config/db.php');

// Generate CSRF token if not exists
if (empty($_SESSION['location_token'])) {
    $_SESSION['location_token'] = bin2hex(random_bytes(32));
}

// Prevent non-admin users from performing any POST operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_admin) {
    $error_message = "You do not have permission to perform this action.";
}

// Check if redirect came from successful insert
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Location added successfully!";
}

// Check if redirect came from successful edit
if (isset($_GET['success']) && $_GET['success'] == '2') {
    $success_message = "Location updated successfully!";
}

// Handle Add Location
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['location_token']) {
        $error_message = "Security token validation failed. Please try again.";
    } else {
        $location_name = trim($_POST['location_name']);
        $description = trim($_POST['description']);
        
        if (!empty($location_name)) {
            $sql = "INSERT INTO equipment_location (location_name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $location_name, $description);
            
            if ($stmt->execute()) {
                $stmt->close();
                // Regenerate token after successful submission
                $_SESSION['location_token'] = bin2hex(random_bytes(32));
                // Redirect to prevent duplicate submission on refresh
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $error_message = "Error adding location: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Location name cannot be empty!";
        }
    }
}

// Handle Delete Location
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $location_id = intval($_POST['location_id']);
    
    $sql = "DELETE FROM equipment_location WHERE location_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $location_id);
    
    if ($stmt->execute()) {
        $success_message = "Location deleted successfully!";
    } else {
        $error_message = "Error deleting location: " . $conn->error;
    }
    $stmt->close();
}

// Handle Edit Location
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['location_token']) {
        $error_message = "Security token validation failed. Please try again.";
    } else {
        $location_id = intval($_POST['location_id']);
        $location_name = trim($_POST['location_name']);
        $description = trim($_POST['description']);
        
        if (!empty($location_name)) {
            $sql = "UPDATE equipment_location SET location_name = ?, description = ? WHERE location_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $location_name, $description, $location_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                // Regenerate token after successful submission
                $_SESSION['location_token'] = bin2hex(random_bytes(32));
                // Redirect to prevent duplicate submission on refresh
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=2");
                exit();
            } else {
                $error_message = "Error updating location: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Location name cannot be empty!";
        }
    }
}

// Fetch all locations
$sql = "SELECT * FROM equipment_location ORDER BY created_at DESC";
$result = $conn->query($sql);
$locations = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Locations - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .location-form {
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
            font-family: inherit;
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

        .btn-submit {
            background: #7f1d1d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
        }

        .btn-submit:hover {
            background: #991b1b;
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

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            border-radius: 6px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            width: 40px;
            height: 40px;
        }

        .btn-edit {
            color: white;
            background: #22c55e;
        }

        .btn-edit:hover {
            background: #16a34a;
            color: white;
        }

        .btn-delete {
            color: white;
            background: var(--danger);
        }

        .btn-delete:hover {
            background: #dc2626;
            color: white;
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

        .locations-table {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .locations-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .locations-table thead {
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }

        .locations-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 14px;
        }

        .locations-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
        }

        .locations-table tbody tr:hover {
            background: var(--gray-50);
        }

        .locations-table tbody tr:last-child td {
            border-bottom: none;
        }

        .location-id {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .location-actions {
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

            .location-actions {
                flex-direction: column;
            }

            .btn-edit, .btn-danger {
                width: 100%;
            }
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

        .form-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .form-row .form-group {
            width: 40%;
            margin-bottom: 0;
        }

        .form-row .btn-submit {
            margin-bottom: 0;
            white-space: nowrap;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--white);
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 500px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--gray-200);
        }

        .modal-header h2 {
            margin: 0;
            color: var(--gray-800);
            font-size: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray-500);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--gray-800);
        }

        .modal-form .form-group {
            margin-bottom: 20px;
        }

        .modal-form .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .modal-form .form-group input,
        .modal-form .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            font-family: inherit;
        }

        .modal-form .form-group input:focus,
        .modal-form .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .btn-cancel {
            background: var(--gray-200);
            color: var(--gray-800);
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: var(--gray-300);
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
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h3><?php echo count($locations); ?></h3>
                        <p>Total Locations</p>
                    </div>
                </div>
            </div>

            <!-- Add Location Button -->
            <?php if ($is_admin): ?>
            <div style="margin-bottom: 30px;">
                <button id="addLocationBtn" class="btn-submit" style="margin: 0;">
                    <i class="fas fa-plus"></i> Add New Location
                </button>
            </div>
            <?php endif; ?>

            <!-- Add Location Modal -->
            <?php if ($is_admin): ?>
            <div id="addLocationModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>
                            <i class="fas fa-plus-circle" style="color: var(--primary-blue); margin-right: 8px;"></i>
                            Add New Location
                        </h2>
                        <button class="modal-close" id="closeModalBtn">&times;</button>
                    </div>

                    <form method="POST" action="" class="modal-form">
                        <div class="form-group">
                            <label for="modal_location_name">Location Name</label>
                            <input 
                                type="text" 
                                id="modal_location_name" 
                                name="location_name" 
                                placeholder="Enter location name (e.g., Building A, Room 101, etc.)"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="modal_description">Description</label>
                            <textarea 
                                id="modal_description" 
                                name="description" 
                                placeholder="Enter location description (optional)"
                                rows="4"
                            ></textarea>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-plus"></i> Add Location
                            </button>
                        </div>

                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['location_token']); ?>">
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit Location Modal -->
            <?php if ($is_admin): ?>
            <div id="editLocationModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>
                            <i class="fas fa-edit" style="color: var(--primary-blue); margin-right: 8px;"></i>
                            Edit Location
                        </h2>
                        <button class="modal-close" id="closeEditModalBtn">&times;</button>
                    </div>

                    <form method="POST" action="" class="modal-form">
                        <div class="form-group">
                            <label for="edit_location_name">Location Name</label>
                            <input 
                                type="text" 
                                id="edit_location_name" 
                                name="location_name" 
                                placeholder="Enter location name"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="edit_description">Description</label>
                            <textarea 
                                id="edit_description" 
                                name="description" 
                                placeholder="Enter location description (optional)"
                                rows="4"
                            ></textarea>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Update Location
                            </button>
                        </div>

                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="location_id" id="edit_location_id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['location_token']); ?>">
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Locations Table -->
            <div class="locations-table">
                <?php if (count($locations) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Location Name</th>
                                <th>Description</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($locations as $location): 
                            ?>
                                <tr>
                                    <td class="location-id">#<?php echo $counter; ?></td>
                                    <td><?php echo htmlspecialchars($location['location_name']); ?></td>
                                    <td>
                                        <span class="created-date">
                                            <?php 
                                            $description = htmlspecialchars($location['description']);
                                            echo strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description;
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="created-date">
                                            <?php echo date('M d, Y H:i', strtotime($location['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_admin): ?>
                                        <button type="button" class="btn-icon btn-edit" onclick="openEditModal(<?php echo $location['location_id']; ?>, '<?php echo htmlspecialchars($location['location_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($location['description'], ENT_QUOTES); ?>');" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this location?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="location_id" value="<?php echo $location['location_id']; ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span style="color: var(--gray-500); font-size: 13px;">No permissions</span>
                                        <?php endif; ?>
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
                        <i class="fas fa-inbox"></i>
                        <h3>No Locations Yet</h3>
                        <p>Start by adding your first equipment location above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="./assets/js/script.js"></script>
    <?php if ($is_admin): ?>
    <script>
        // Get modal elements
        const addLocationModal = document.getElementById('addLocationModal');
        const addLocationBtn = document.getElementById('addLocationBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const modalForm = addLocationModal.querySelector('form');

        // Open modal
        addLocationBtn.addEventListener('click', function() {
            addLocationModal.classList.add('active');
            document.getElementById('modal_location_name').focus();
        });

        // Close modal
        function closeModal() {
            addLocationModal.classList.remove('active');
            modalForm.reset();
        }

        closeModalBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        // Close modal when clicking outside of it
        addLocationModal.addEventListener('click', function(event) {
            if (event.target === addLocationModal) {
                closeModal();
            }
        });

        // Prevent modal close when clicking inside modal content
        addLocationModal.querySelector('.modal-content').addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // ========== Edit Location Modal ==========
        const editLocationModal = document.getElementById('editLocationModal');
        const closeEditModalBtn = document.getElementById('closeEditModalBtn');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        const editModalForm = editLocationModal.querySelector('form');

        // Open edit modal
        function openEditModal(locationId, locationName, description) {
            document.getElementById('edit_location_id').value = locationId;
            document.getElementById('edit_location_name').value = locationName;
            document.getElementById('edit_description').value = description;
            editLocationModal.classList.add('active');
            document.getElementById('edit_location_name').focus();
        }

        // Close edit modal
        function closeEditModal() {
            editLocationModal.classList.remove('active');
            editModalForm.reset();
        }

        closeEditModalBtn.addEventListener('click', closeEditModal);
        cancelEditBtn.addEventListener('click', closeEditModal);

        // Close modal when clicking outside of it
        editLocationModal.addEventListener('click', function(event) {
            if (event.target === editLocationModal) {
                closeEditModal();
            }
        });

        // Prevent modal close when clicking inside modal content
        editLocationModal.querySelector('.modal-content').addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
    <?php endif; ?>
</body>
</html>
