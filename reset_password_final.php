<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';

// Check database connection
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$code = isset($input['code']) ? trim($input['code']) : '';
$password = isset($input['password']) ? $input['password'] : '';

if ($email === '' || $code === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!isset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_expires_at'])) {
    echo json_encode(['success' => false, 'message' => 'No reset code found. Please request a new one.']);
    exit;
}

if ($email !== $_SESSION['reset_email']) {
    echo json_encode(['success' => false, 'message' => 'Email does not match the reset request']);
    exit;
}

if (time() > (int)$_SESSION['reset_otp_expires_at']) {
    echo json_encode(['success' => false, 'message' => 'The reset code has expired. Please request a new one.']);
    exit;
}

// Verify OTP and Session Status
if ($code !== $_SESSION['reset_otp'] || empty($_SESSION['reset_otp_verified'])) {
    echo json_encode(['success' => false, 'message' => 'Verification required before resetting password']);
    exit;
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$query = "UPDATE users SET passwords = ? WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $hashed, $email);
$execute_result = mysqli_stmt_execute($stmt);

if (!$execute_result) {
    echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . mysqli_stmt_error($stmt)]);
    mysqli_stmt_close($stmt);
    exit;
}

// Note: mysqli_stmt_affected_rows can be 0 if the new password is the same as the old one.
// We consider this a success because the intent (setting the password) is fulfilled.

mysqli_stmt_close($stmt);

// Clear reset session data
unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_expires_at'], $_SESSION['reset_otp_verified']);

echo json_encode(['success' => true]);
?>
