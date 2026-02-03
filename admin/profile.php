<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Database connection
include('../config/db.php');

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? null;

// Handle profile update
if (isset($_POST['update_profile'])) {
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $gander = mysqli_real_escape_string($conn, $_POST['gander']);
    
    // Handle image upload
    $image_updated = false;
    $new_image_name = '';
    
    if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] == 0) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['user_image']['name'];
        $file_size = $_FILES['user_image']['size'];
        $file_tmp = $_FILES['user_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed_extensions) && $file_size <= 5000000) {
            // Get current image
            $get_image_query = "SELECT user_image FROM users WHERE user_id = '$user_id'";
            $image_result = mysqli_query($conn, $get_image_query);
            $image_row = mysqli_fetch_assoc($image_result);
            $old_image = $image_row['user_image'];
            
            // Delete old image if exists
            if (!empty($old_image) && file_exists("../uploads/users/" . $old_image)) {
                unlink("../uploads/users/" . $old_image);
            }
            
            // Upload new image
            $new_image_name = time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = "../uploads/users/" . $new_image_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_updated = true;
            }
        } else {
            $_SESSION['error_message'] = "Invalid file format or size too large (max 5MB)";
        }
    }
    
    // Update query
    if ($image_updated) {
        $update_query = "UPDATE users SET 
            firstname = '$firstname',
            lastname = '$lastname',
            email = '$email',
            username = '$username',
            gander = '$gander',
            user_image = '$new_image_name',
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = '$user_id'";
    } else {
        $update_query = "UPDATE users SET 
            firstname = '$firstname',
            lastname = '$lastname',
            email = '$email',
            username = '$username',
            gander = '$gander',
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = '$user_id'";
    }
    
    if (mysqli_query($conn, $update_query)) {
        // Update session variables
        $_SESSION['username'] = $username;
        $_SESSION['firstname'] = $firstname;
        $_SESSION['lastname'] = $lastname;
        if ($image_updated) {
            $_SESSION['user_image'] = $new_image_name;
        }
        $_SESSION['success_message'] = "Profile updated successfully";
    } else {
        $_SESSION['error_message'] = "Failed to update profile";
    }
    
    header("Location: profile.php");
    exit();
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current password from database
    $pass_query = "SELECT passwords FROM users WHERE user_id = '$user_id'";
    $pass_result = mysqli_query($conn, $pass_query);
    $pass_row = mysqli_fetch_assoc($pass_result);
    
    if (password_verify($current_password, $pass_row['passwords'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pass = "UPDATE users SET passwords = '$hashed_password', updated_at = CURRENT_TIMESTAMP WHERE user_id = '$user_id'";
                
                if (mysqli_query($conn, $update_pass)) {
                    $_SESSION['success_message'] = "Password changed successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to change password";
                }
            } else {
                $_SESSION['error_message'] = "Password must be at least 6 characters";
            }
        } else {
            $_SESSION['error_message'] = "Passwords do not match";
        }
    } else {
        $_SESSION['error_message'] = "Current password is incorrect";
    }
    
    header("Location: profile.php");
    exit();
}

// Fetch user data
$user_data = [];
if ($user_id) {
    $query = "SELECT u.*, r.role_name 
              FROM users u 
              LEFT JOIN role r ON u.role_id = r.role_id 
              WHERE u.user_id = '$user_id'";
    $result = mysqli_query($conn, $query);
    $user_data = mysqli_fetch_assoc($result);
}

// Get user image
$userImage = $user_data['user_image'] ?? '';
$userImagePath = '../uploads/users/' . $userImage;
$userImageFallback = '../static/images/default-user.png';
$userImageSrc = (!empty($userImage) && file_exists($userImagePath)) ? $userImagePath : $userImageFallback;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <link rel="stylesheet" href="./assets/css/profile.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
    <?php include './include/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <?php include './include/header.php'; ?>

        <!-- Profile Content -->
        <div class="dashboard-content">
            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-image-container">
                        <img src="<?php echo htmlspecialchars($userImageSrc); ?>" 
                             alt="Profile" 
                             class="profile-image" 
                             id="profileImageDisplay">
                        <div class="upload-overlay" id="uploadOverlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div class="profile-header-info">
                        <h1><?php echo htmlspecialchars($user_data['firstname'] . ' ' . $user_data['lastname']); ?></h1>
                        <p><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($user_data['role_name'] ?? 'User'); ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_data['email']); ?></p>
                        <p><i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user_data['created_at'])); ?></p>
                    </div>
                </div>

                <!-- Profile Content Grid -->
                <div class="profile-content">
                    <!-- Left Column -->
                    <div>
                        <!-- Personal Information -->
                        <div class="info-section">
                            <div class="section-header">
                                <h2><i class="fas fa-user"></i> Personal Information</h2>
                                <button class="edit-btn" id="editBtn">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>

                            <!-- Display Mode -->
                            <div id="displayMode">
                                <div class="info-group">
                                    <div class="info-label">First Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_data['firstname']); ?></div>
                                </div>

                                <div class="info-group">
                                    <div class="info-label">Last Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_data['lastname']); ?></div>
                                </div>

                                <div class="info-group">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></div>
                                </div>

                                <div class="info-group">
                                    <div class="info-label">Username</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                                </div>

                                <div class="info-group">
                                    <div class="info-label">Gender</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_data['gander'] ?? 'Not specified'); ?></div>
                                </div>
                            </div>

                            <!-- Edit Mode -->
                            <form method="POST" enctype="multipart/form-data" id="editMode" class="hidden">
                                <div class="form-group">
                                    <label>Profile Image</label>
                                    <input type="file" 
                                           name="user_image" 
                                           id="userImageInput" 
                                           accept="image/*"
                                           class="image-upload-container">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <img src="<?php echo htmlspecialchars($userImageSrc); ?>" 
                                             alt="Preview" 
                                             id="imagePreview"
                                             style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #e0e0e0;">
                                        <button type="button" 
                                                onclick="document.getElementById('userImageInput').click()" 
                                                class="edit-btn"
                                                style="margin: 0;">
                                            <i class="fas fa-upload"></i> Change Image
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" 
                                           name="firstname" 
                                           value="<?php echo htmlspecialchars($user_data['firstname']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" 
                                           name="lastname" 
                                           value="<?php echo htmlspecialchars($user_data['lastname']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" 
                                           name="username" 
                                           value="<?php echo htmlspecialchars($user_data['username']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gander" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo ($user_data['gander'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($user_data['gander'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($user_data['gander'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="save-btn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="cancel-btn" id="cancelBtn">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Change Password -->
                        <div class="password-section">
                            <div class="section-header">
                                <h2><i class="fas fa-lock"></i> Change Password</h2>
                            </div>

                            <form method="POST">
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input type="password" name="current_password" required>
                                </div>

                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" required>
                                </div>

                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" required>
                                </div>

                                <button type="submit" name="change_password" class="save-btn">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div>
                        <!-- Account Stats -->
                        <div class="stats-card">
                            <h3><i class="fas fa-chart-bar"></i> Account Statistics</h3>
                            <div class="stat-item">
                                <span class="stat-label">Account Status</span>
                                <span class="stat-value" style="color: #27ae60;">
                                    <?php echo htmlspecialchars($user_data['statuss'] ?? 'Active'); ?>
                                </span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Account Created</span>
                                <span class="stat-value">
                                    <?php echo date('M d, Y', strtotime($user_data['created_at'])); ?>
                                </span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Last Updated</span>
                                <span class="stat-value">
                                    <?php echo date('M d, Y', strtotime($user_data['updated_at'])); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Account Info -->
                        <div class="stats-card">
                            <h3><i class="fas fa-info-circle"></i> Account Details</h3>
                            <div class="stat-item">
                                <span class="stat-label">User ID</span>
                                <span class="stat-value">#<?php echo htmlspecialchars($user_data['user_id']); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Role</span>
                                <span class="stat-value"><?php echo htmlspecialchars($user_data['role_name'] ?? 'User'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
<script src="./assets/js/script.js"></script>    
    <script>
        // Toggle edit mode
        const editBtn = document.getElementById('editBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const displayMode = document.getElementById('displayMode');
        const editMode = document.getElementById('editMode');
        const uploadOverlay = document.getElementById('uploadOverlay');

        editBtn.addEventListener('click', () => {
            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
            editBtn.classList.add('hidden');
            uploadOverlay.style.display = 'flex';
        });

        cancelBtn.addEventListener('click', () => {
            displayMode.classList.remove('hidden');
            editMode.classList.add('hidden');
            editBtn.classList.remove('hidden');
            uploadOverlay.style.display = 'none';
        });

        // Image preview
        document.getElementById('userImageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('profileImageDisplay').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Upload overlay click
        uploadOverlay.addEventListener('click', () => {
            document.getElementById('userImageInput').click();
        });

        // Show success/error messages
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $_SESSION['success_message']; ?>',
                timer: 2000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo $_SESSION['error_message']; ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>