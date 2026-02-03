<?php
session_start();

// Include database configuration
require_once 'config/db.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // Get form inputs
    $login_input = trim($_POST['email']); // Can be username or email
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($login_input) || empty($password)) {
        header("Location: index.html?error=" . urlencode("Please fill in all fields"));
        exit();
    }
    
    // Query database for user with matching username OR email (including role)
    $query = "SELECT u.user_id, u.username, u.passwords, u.statuss, u.user_image, u.role_id, r.role_name
              FROM users u
              LEFT JOIN role r ON u.role_id = r.role_id
              WHERE (u.username = ? OR u.email = ?) LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        header("Location: index.html?error=" . urlencode("Database error"));
        exit();
    }
    
    // Bind parameters (bind twice for username and email checks)
    mysqli_stmt_bind_param($stmt, "ss", $login_input, $login_input);
    
    // Execute query
    mysqli_stmt_execute($stmt);
    
    // Get result
    $result = mysqli_stmt_get_result($stmt);
    
    // Check if user exists
    if (mysqli_num_rows($result) === 1) {
        
        $user = mysqli_fetch_assoc($result);
        
        // Verify password (support both plain text and hashed passwords)
        $password_match = false;
        
        // Check if password matches (plain text)
        if ($password === $user['passwords']) {
            $password_match = true;
        }
        // Check if password matches (hashed with password_hash)
        elseif (password_verify($password, $user['passwords'])) {
            $password_match = true;
        }
        
        if ($password_match) {
            
            // Check if user account is active (statuss must equal 1)
            if ($user['statuss'] == 1) {
                
                // Create session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_image'] = isset($user['user_image']) ? $user['user_image'] : '';
                $_SESSION['role_id'] = isset($user['role_id']) ? $user['role_id'] : '';
                $_SESSION['user_role'] = isset($user['role_name']) && $user['role_name'] !== '' ? $user['role_name'] : 'User Account';
                $_SESSION['logged_in'] = true;
                
                // Redirect to dashboard
                header("Location: admin/dashboard.php");
                exit();
                
            } else {
                // Account is inactive
                header("Location: index.html?error=" . urlencode("Your account has been deactivated. Please contact the administrator."));
                exit();
            }
            
        } else {
            // Password is incorrect
            header("Location: index.html?error=" . urlencode("incorrect_password"));
            exit();
        }
        
    } else {
        // User not found
        header("Location: index.html?error=" . urlencode("incorrect_password"));
        exit();
    }
    
    // Close statement
    mysqli_stmt_close($stmt);
    
} else {
    // If not a POST request, redirect to login
    header("Location: index.html");
    exit();
}

// Close database connection
mysqli_close($conn);
?>
