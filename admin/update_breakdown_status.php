<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

include('../config/db.php');

$role_id = $_SESSION['role_id'];

// Verify user is admin
$admin_role_id = null;
$role_sql = "SELECT role_id FROM role WHERE role_name = 'Admin' LIMIT 1";
$role_result = $conn->query($role_sql);
if ($role_result && $role_result->num_rows > 0) {
    $role_row = $role_result->fetch_assoc();
    $admin_role_id = $role_row['role_id'];
}

$is_admin = ($role_id == $admin_role_id);

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Only admins can change status']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['breakdown_id']) || !isset($input['new_status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$breakdown_id = intval($input['breakdown_id']);
$new_status = $input['new_status'];

// Validate new status
if (!in_array($new_status, ['active', 'Open'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

// Update the breakdown status
$update_sql = "UPDATE breakdown SET statuss = ?, updated_at = NOW() WHERE breakdown_id = ?";
$stmt = $conn->prepare($update_sql);

if ($stmt) {
    $stmt->bind_param('si', $new_status, $breakdown_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully', 'new_status' => $new_status]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Breakdown not found']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>