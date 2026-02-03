<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$code = isset($input['code']) ? trim($input['code']) : '';

if ($email === '' || $code === '') {
    echo json_encode(['success' => false, 'message' => 'Missing email or code']);
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

if ($code !== $_SESSION['reset_otp']) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
    exit;
}

$_SESSION['reset_otp_verified'] = true;
echo json_encode(['success' => true]);
