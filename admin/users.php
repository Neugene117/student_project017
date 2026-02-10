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
include('../lib/mailer.php');

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
                u.statuss,
                u.created_at, 
                r.role_name,
                u.role_id 
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
    $statuss = '1'; // Default status
    
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
    
    $sql = "INSERT INTO users (firstname, lastname, username, email, passwords, role_id, gander, user_image, statuss) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssisss", $firstname, $lastname, $username, $email, $hashed_password, $role_id, $gander, $user_image, $statuss);
    
    if ($stmt->execute()) {
        // Send Welcome Email with Credentials
        $subject = "Welcome to Equipment Management System - Your Credentials";
        
        // Get Role Name
        $role_name_query = "SELECT role_name FROM role WHERE role_id = '$role_id'";
        $role_result = mysqli_query($conn, $role_name_query);
        $role_row = mysqli_fetch_assoc($role_result);
        $role_name = $role_row['role_name'] ?? 'User';

        $login_url = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/index.html";

        $emailBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px; background-color: #f9f9f9;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='cid:company_logo' alt='Company Logo' style='max-width: 150px; height: auto;'>
                <h2 style='color: #740101; margin-top: 10px;'>Welcome to Equipment Management System</h2>
            </div>
            
            <div style='background-color: #ffffff; padding: 20px; border-radius: 5px; border-left: 4px solid #740101;'>
                <p>Dear <strong>" . htmlspecialchars($firstname . ' ' . $lastname) . "</strong>,</p>
                
                <p>Your account has been successfully created. You have been assigned the role of <strong>" . htmlspecialchars($role_name) . "</strong>.</p>
                
                <p>Below are your login credentials:</p>
                
                <div style='background-color: #f0f2f5; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p style='margin: 5px 0;'><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                    <p style='margin: 5px 0;'><strong>Password:</strong> " . htmlspecialchars($password) . "</p>
                    <p style='margin: 5px 0;'><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                </div>
                
                <p>Please login and change your password immediately for security purposes.</p>
                
            </div>
            
            <div style='margin-top: 20px; font-size: 12px; color: #666; text-align: center;'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " Equipment Management System. All rights reserved.</p>
            </div>
        </div>";

        $mailResult = send_app_mail($email, $firstname . ' ' . $lastname, $subject, $emailBody);
        if (!$mailResult['success']) {
             header("Location: users.php?success=User added successfully (Warning: Email failed: " . urlencode($mailResult['error']) . ")");
        } else {
             header("Location: users.php?success=User added successfully");
        }
        exit();
    } else {
        $error = "Error adding user: " . $conn->error;
    }
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role_id = mysqli_real_escape_string($conn, $_POST['role_id']);
    $gander = mysqli_real_escape_string($conn, $_POST['gander']);
    $statuss = mysqli_real_escape_string($conn, $_POST['statuss']);
    
    // Check if password is provided
    $password_clause = "";
    if (!empty($_POST['password'])) {
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_clause = ", passwords = '$hashed_password'";
    }
    
    // Handle image upload
    $image_clause = "";
    if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['user_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['user_image']['tmp_name'], '../uploads/users/' . $new_filename)) {
                $image_clause = ", user_image = '$new_filename'";
            }
        }
    }
    
    $sql = "UPDATE users SET firstname = ?, lastname = ?, username = ?, email = ?, role_id = ?, gander = ?, statuss = ? $password_clause $image_clause WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssissi", $firstname, $lastname, $username, $email, $role_id, $gander, $statuss, $user_id);
    
    if ($stmt->execute()) {
        header("Location: users.php?success=User updated successfully");
        exit();
    } else {
        $error = "Error updating user: " . $conn->error;
    }
}

// Handle Delete User
if (isset($_GET['delete_user'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete_user']);
    
    // Prevent deleting self
    if ($delete_id == $_SESSION['user_id']) {
        header("Location: users.php?error=You cannot delete your own account");
        exit();
    }
    
    $sql = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        header("Location: users.php?success=User deleted successfully");
        exit();
    } else {
        header("Location: users.php?error=Error deleting user");
        exit();
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
                                <th>Status</th>
                                <th>Actions</th>
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
                                <td>
                                    <?php 
                                    $status = $user['statuss'] ?? 'Active';
                                    $badgeClass = ($status == 'Active') ? 'badge-success' : 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-icon edit-btn" onclick='openEditModal(<?php echo json_encode($user); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <a href="users.php?delete_user=<?php echo $user['user_id']; ?>" class="btn-icon delete-btn" onclick="return confirm('Are you sure you want to delete this user?');" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
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
            <form method="POST" action="" enctype="multipart/form-data" id="addUserForm">
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
                    <input type="hidden" name="add_user" value="1">
                    <button type="submit" name="add_user" class="btn-submit">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="editUserForm">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_firstname">First Name</label>
                            <input type="text" id="edit_firstname" name="firstname" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_lastname">Last Name</label>
                            <input type="text" id="edit_lastname" name="lastname" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_password">Password (Leave blank to keep current)</label>
                            <input type="password" id="edit_password" name="password">
                        </div>
                        <div class="form-group">
                            <label for="edit_role_id">Role</label>
                            <select id="edit_role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles_list as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_gander">Gender</label>
                            <select id="edit_gander" name="gander" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_statuss">Status</label>
                            <select id="edit_statuss" name="statuss" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_user_image">Profile Image</label>
                            <input type="file" id="edit_user_image" name="user_image" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <input type="hidden" name="edit_user" value="1">
                    <button type="submit" name="edit_user" class="btn-submit">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="./assets/js/script.js"></script>
    <script>
        // Loading State for Add User Form
        const addUserForm = document.getElementById('addUserForm');
        if (addUserForm) {
            addUserForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return;
                }
                // Save original text
                const originalText = submitBtn.innerHTML;
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            });
        }

        // Loading State for Edit User Form
        const editUserForm = document.getElementById('editUserForm');
        if (editUserForm) {
            editUserForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return;
                }
                // Save original text
                const originalText = submitBtn.innerHTML;
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            });
        }

        // Modal functions
        const modal = document.getElementById('addUserModal');
        const editModal = document.getElementById('editUserModal');

        function openModal() {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_firstname').value = user.firstname;
            document.getElementById('edit_lastname').value = user.lastname;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role_id').value = user.role_id;
            document.getElementById('edit_gander').value = user.gander;
            document.getElementById('edit_statuss').value = user.statuss || '1';
            
            editModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            editModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>