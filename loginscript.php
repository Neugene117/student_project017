<?php
session_start();

// Include database configuration
require_once 'config/db.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // Get form inputs
    $username = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        header("Location: index.html?error=" . urlencode("Please fill in all fields"));
        exit();
    }
    
    // Query database for user with matching username
    $query = "SELECT username, passwords, statuss FROM users WHERE username = ? LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        header("Location: index.html?error=" . urlencode("Database error"));
        exit();
    }
    
    // Bind parameters
    mysqli_stmt_bind_param($stmt, "s", $username);
    
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
                $_SESSION['username'] = $user['username'];
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
