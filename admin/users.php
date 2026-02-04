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

// Function to get all users
function getAllUsers($conn) {
    $sql = "SELECT 
                u.user_id, 
                u.firstname, 
                u.lastname, 
                u.email, 
                u.username, 
                u.gander, 
                u.user_image, 
                u.created_at, 
                r.role_name 
            FROM users u 
            LEFT JOIN role r ON u.role_id = r.role_id 
            ORDER BY u.user_id ASC";
    
    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to get all roles for the modal
function getAllRoles($conn) {
    $sql = "SELECT role_id, role_name FROM role ORDER BY role_name";
    $result = $conn->query($sql);
    $roles = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
    }
    return $roles;
}

// Handle Add User
if (isset($_POST['add_user'])) {
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role_id = mysqli_real_escape_string($conn, $_POST['role_id']);
    $gander = mysqli_real_escape_string($conn, $_POST['gander']);
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Handle image upload
    $user_image = '';
    if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['user_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['user_image']['tmp_name'], '../uploads/users/' . $new_filename)) {
                $user_image = $new_filename;
            }
        }
    }
    
    $sql = "INSERT INTO users (firstname, lastname, username, email, passwords, role_id, gander, user_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssiss", $firstname, $lastname, $username, $email, $hashed_password, $role_id, $gander, $user_image);
    
    if ($stmt->execute()) {
        header("Location: users.php?success=User added successfully");
        exit();
    } else {
        $error = "Error adding user: " . $conn->error;
    }
}

$users_list = getAllUsers($conn);
$roles_list = getAllRoles($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Reports - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <link rel="stylesheet" href="./assets/css/users.css">
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
                <h1>User Reports - Equipment Management System</h1>
            </div>

            <!-- Filter Section (Simplified for Actions) -->
            <div class="filter-section">
                <div class="filter-header">
                    <i class="fas fa-users"></i>
                    <h2>User Report</h2>
                    <div style="flex-grow: 1;"></div>
                    <div class="filter-actions">
                        <button type="button" class="btn-add" onclick="openModal()">
                            <i class="fas fa-plus"></i> Add New User
                        </button>
                        <button type="button" class="btn-export" onclick="window.print()">
                            <i class="fas fa-file-pdf"></i> Export to PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- User Report Table -->
            <div class="report-section">
                <div class="report-header">
                    <h3>
                        <i class="fas fa-list-ul"></i>
                        All Users Overview
                    </h3>
                    <span class="badge badge-info">
                        As of <?php echo date('M d, Y'); ?>
                    </span>
                </div>

                <div class="report-table">
                    <?php if (!empty($users_list)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Gender</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_list as $user): ?>
                            <tr>
                                <td>#<?php echo $user['user_id']; ?></td>
                                <td>
                                    <?php if (!empty($user['user_image']) && file_exists("../uploads/users/" . $user['user_image'])): ?>
                                        <img src="../uploads/users/<?php echo htmlspecialchars($user['user_image']); ?>" alt="User Image" class="user-avatar">
                                    <?php else: ?>
                                        <div class="user-avatar" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo htmlspecialchars($user['role_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['gander'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Users Found</h3>
                        <p>No users available in the system.</p>
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

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstname">First Name</label>
                            <input type="text" id="firstname" name="firstname" required>
                        </div>
                        <div class="form-group">
                            <label for="lastname">Last Name</label>
                            <input type="text" id="lastname" name="lastname" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="role_id">Role</label>
                            <select id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles_list as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gander">Gender</label>
                            <select id="gander" name="gander" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="user_image">Profile Image</label>
                            <input type="file" id="user_image" name="user_image" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="add_user" class="btn-submit">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="./assets/js/script.js"></script>
    <script>
        // Modal functions
        const modal = document.getElementById('addUserModal');

        function openModal() {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>