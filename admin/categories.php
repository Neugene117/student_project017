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

// Generate CSRF token if not exists
if (empty($_SESSION['category_token'])) {
    $_SESSION['category_token'] = bin2hex(random_bytes(32));
}

// Check if redirect came from successful insert
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Category added successfully!";
}

// Check if redirect came from successful edit
if (isset($_GET['success']) && $_GET['success'] == '2') {
    $success_message = "Category updated successfully!";
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

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['category_token']) {
        $error_message = "Security token validation failed. Please try again.";
    } else {
        $category_id = intval($_POST['category_id']);
        $category_name = trim($_POST['category_name']);
        
        if (!empty($category_name)) {
            $sql = "UPDATE category SET category_name = ? WHERE category_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $category_name, $category_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                // Regenerate token after successful submission
                $_SESSION['category_token'] = bin2hex(random_bytes(32));
                // Redirect to prevent duplicate submission on refresh
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=2");
                exit();
            } else {
                $error_message = "Error updating category: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Category name cannot be empty!";
        }
    }
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
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
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

        .modal-form .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }

        .modal-form .form-group input:focus {
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
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h3><?php echo count($categories); ?></h3>
                        <p>Total Categories</p>
                    </div>
                </div>
            </div>

            <!-- Add Category Button -->
            <div style="margin-bottom: 30px;">
                <button id="addCategoryBtn" class="btn-submit" style="margin: 0;">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>

            <!-- Add Category Modal -->
            <div id="addCategoryModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>
                            <i class="fas fa-plus-circle" style="color: var(--primary-blue); margin-right: 8px;"></i>
                            Add New Category
                        </h2>
                        <button class="modal-close" id="closeModalBtn">&times;</button>
                    </div>

                    <form method="POST" action="" class="modal-form">
                        <div class="form-group">
                            <label for="modal_category_name">Category Name</label>
                            <input 
                                type="text" 
                                id="modal_category_name" 
                                name="category_name" 
                                placeholder="Enter category name (e.g., Medical Equipment, Tools, etc.)"
                                required
                            >
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>

                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['category_token']); ?>">
                    </form>
                </div>
            </div>

            <!-- Edit Category Modal -->
            <div id="editCategoryModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>
                            <i class="fas fa-edit" style="color: var(--primary-blue); margin-right: 8px;"></i>
                            Edit Category
                        </h2>
                        <button class="modal-close" id="closeEditModalBtn">&times;</button>
                    </div>

                    <form method="POST" action="" class="modal-form">
                        <div class="form-group">
                            <label for="edit_category_name">Category Name</label>
                            <input 
                                type="text" 
                                id="edit_category_name" 
                                name="category_name" 
                                placeholder="Enter category name"
                                required
                            >
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Update Category
                            </button>
                        </div>

                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="category_id" id="edit_category_id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['category_token']); ?>">
                    </form>
                </div>
            </div>
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
                            <?php 
                            $counter = 1;
                            foreach ($categories as $category): 
                            ?>
                                <tr>
                                    <td class="category-id">#<?php echo $counter; ?></td>
                                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td>
                                        <span class="created-date">
                                            <?php echo date('M d, Y H:i', strtotime($category['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-icon btn-edit" onclick="openEditModal(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['category_name'], ENT_QUOTES); ?>');" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
                        <h3>No Categories Yet</h3>
                        <p>Start by adding your first equipment category above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    <script>
        // Get modal elements
        const addCategoryModal = document.getElementById('addCategoryModal');
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const modalForm = addCategoryModal.querySelector('form');

        // Open modal
        addCategoryBtn.addEventListener('click', function() {
            addCategoryModal.classList.add('active');
            document.getElementById('modal_category_name').focus();
        });

        // Close modal
        function closeModal() {
            addCategoryModal.classList.remove('active');
            modalForm.reset();
        }

        closeModalBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        // Close modal when clicking outside of it
        addCategoryModal.addEventListener('click', function(event) {
            if (event.target === addCategoryModal) {
                closeModal();
            }
        });

        // Prevent modal close when clicking inside modal content
        addCategoryModal.querySelector('.modal-content').addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // ========== Edit Category Modal ==========
        const editCategoryModal = document.getElementById('editCategoryModal');
        const closeEditModalBtn = document.getElementById('closeEditModalBtn');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        const editModalForm = editCategoryModal.querySelector('form');

        // Open edit modal
        function openEditModal(categoryId, categoryName) {
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_category_name').value = categoryName;
            editCategoryModal.classList.add('active');
            document.getElementById('edit_category_name').focus();
        }

        // Close edit modal
        function closeEditModal() {
            editCategoryModal.classList.remove('active');
            editModalForm.reset();
        }

        closeEditModalBtn.addEventListener('click', closeEditModal);
        cancelEditBtn.addEventListener('click', closeEditModal);

        // Close modal when clicking outside of it
        editCategoryModal.addEventListener('click', function(event) {
            if (event.target === editCategoryModal) {
                closeEditModal();
            }
        });

        // Prevent modal close when clicking inside modal content
        editCategoryModal.querySelector('.modal-content').addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
</body>
</html>
