<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/mailer.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// Check if user exists by email
$query = "SELECT username, email FROM users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    echo json_encode(['success' => false, 'message' => 'Email not found in our system']);
    exit;
}

$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Generate OTP
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = time() + 10 * 60; // 10 minutes

$_SESSION['reset_email'] = $email;
$_SESSION['reset_otp'] = $otp;
$_SESSION['reset_otp_expires_at'] = $expiresAt;
$_SESSION['reset_otp_verified'] = false;

// Send email
$subject = 'Your Password Reset Code';
$htmlBody = "
    <p>Hello " . htmlspecialchars($user['username']) . ",</p>
    <p>Your password reset code is:</p>
    <h2 style=\"letter-spacing: 2px;\">{$otp}</h2>
    <p>This code will expire in 10 minutes.</p>
    <p>If you did not request this, you can ignore this email.</p>
";

$sendResult = send_app_mail($email, $user['username'], $subject, $htmlBody);

if (!$sendResult['success']) {
    echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $sendResult['error']]);
    exit;
}

echo json_encode(['success' => true]);
