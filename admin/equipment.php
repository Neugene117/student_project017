<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Include database connection
require_once '../config/db.php';

// Get user role from session
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Check if user is admin
if ($user_role !== 'admin') {
    header("Location: dashboard.php?error=" . urlencode("You do not have permission to access this page"));
    exit();
}

// Handle Get Equipment Details (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'get_equipment_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $equipment_id = (int)$_GET['id'];
    $query = "SELECT * FROM equipment WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $equipment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($data = mysqli_fetch_assoc($result)) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Equipment not found']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';
$equipment_list = [];
$categories = [];
$locations = [];

// Check for success message in session (from redirect)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear after displaying
}

// Check for error message in session (from redirect)
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear after displaying
}

// Fetch categories
$category_query = "SELECT category_id, category_name FROM category ORDER BY category_name ASC";
$category_result = mysqli_query($conn, $category_query);
if ($category_result) {
    while ($row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $row;
    }
}

// Fetch locations
$location_query = "SELECT location_id, location_name FROM equipment_location ORDER BY location_name ASC";
$location_result = mysqli_query($conn, $location_query);
if ($location_result) {
    while ($row = mysqli_fetch_assoc($location_result)) {
        $locations[] = $row;
    }
}

// Handle Insert Equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_equipment') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid request. Please try again.";
    } else {
        // Sanitize and validate input
        $equipment_name = isset($_POST['equipment_name']) ? trim($_POST['equipment_name']) : '';
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';
        $equipment_location_id = isset($_POST['equipment_location_id']) ? (int)$_POST['equipment_location_id'] : 0;
        $purchase_date = isset($_POST['purchase_date']) ? $_POST['purchase_date'] : '';
        $has_warranty = isset($_POST['has_warranty']) ? $_POST['has_warranty'] : 'no';
        
        // Handle warranty dates - set to NULL if no warranty
        $starting_date = null;
        $expired_date = null;
        
        if ($has_warranty === 'yes') {
            $starting_date = isset($_POST['starting_date']) && !empty($_POST['starting_date']) ? $_POST['starting_date'] : null;
            $expired_date = isset($_POST['expired_date']) && !empty($_POST['expired_date']) ? $_POST['expired_date'] : null;
        }
        
        $statuss = isset($_POST['statuss']) ? trim($_POST['statuss']) : 'Active';
        
        // Handle file upload for equipment image
        $equipment_image = '';
        if (isset($_FILES['equipment_image']) && $_FILES['equipment_image']['size'] > 0) {
            $file = $_FILES['equipment_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error_message = "Invalid file type. Please upload an image file (JPG, PNG, GIF, WebP).";
            } elseif ($file['size'] > $max_size) {
                $error_message = "File size exceeds 5MB limit.";
            } else {
                // Create uploads directory if it doesn't exist
                if (!is_dir('../uploads/equipment')) {
                    mkdir('../uploads/equipment', 0755, true);
                }
                
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $equipment_image = 'equipment_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = '../uploads/equipment/' . $equipment_image;
                
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    $error_message = "Failed to upload image. Please try again.";
                    $equipment_image = '';
                }
            }
        }
        
        // Validate required fields
        if (empty($equipment_name)) {
            $_SESSION['error_message'] = "Equipment name is required.";
            header("Location: equipment.php");
            exit();
        } elseif ($category_id <= 0) {
            $_SESSION['error_message'] = "Please select a valid category.";
            header("Location: equipment.php");
            exit();
        } elseif ($equipment_location_id <= 0) {
            $_SESSION['error_message'] = "Please select a valid location.";
            header("Location: equipment.php");
            exit();
        } elseif (!empty($serial_number)) {
            // Check for duplicate serial number only if serial number is provided
            $duplicate_check_query = "SELECT id FROM equipment WHERE serial_number = ?";
            $stmt_check = mysqli_prepare($conn, $duplicate_check_query);
            if ($stmt_check) {
                mysqli_stmt_bind_param($stmt_check, "s", $serial_number);
                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                
                if (mysqli_num_rows($result_check) > 0) {
                    $_SESSION['error_message'] = "An equipment with this serial number already exists. Serial numbers must be unique.";
                    header("Location: equipment.php");
                    exit();
                }
                mysqli_stmt_close($stmt_check);
            }
        }
        
        // If we reach here, all validations have passed
        // Insert into database
        $insert_query = "INSERT INTO equipment (equipment_name, equipment_image, category_id, serial_number, 
                        equipment_location_id, purchase_date, starting_date, expired_date, statuss, user_id, 
                        created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = mysqli_prepare($conn, $insert_query);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssisssdssi",
                $equipment_name,
                $equipment_image,
                $category_id,
                $serial_number,
                $equipment_location_id,
                $purchase_date,
                $starting_date,
                $expired_date,
                $statuss,
                $user_id
            );
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                // Store success message in session and redirect to prevent duplicate submissions on refresh
                $_SESSION['success_message'] = "Equipment added successfully!";
                header("Location: equipment.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error adding equipment: " . mysqli_error($conn);
                header("Location: equipment.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Database error: " . mysqli_error($conn);
            header("Location: equipment.php");
            exit();
        }
    }
}

// Handle Update Equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_equipment') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid request. Please try again.";
    } else {
        $equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
        
        if ($equipment_id <= 0) {
            $_SESSION['error_message'] = "Invalid equipment ID.";
            header("Location: equipment.php");
            exit();
        }

        // Sanitize and validate input
        $equipment_name = isset($_POST['equipment_name']) ? trim($_POST['equipment_name']) : '';
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';
        $equipment_location_id = isset($_POST['equipment_location_id']) ? (int)$_POST['equipment_location_id'] : 0;
        $purchase_date = isset($_POST['purchase_date']) ? $_POST['purchase_date'] : '';
        $has_warranty = isset($_POST['has_warranty']) ? $_POST['has_warranty'] : 'no';
        
        // Handle warranty dates - set to NULL if no warranty
        $starting_date = null;
        $expired_date = null;
        
        if ($has_warranty === 'yes') {
            $starting_date = isset($_POST['starting_date']) && !empty($_POST['starting_date']) ? $_POST['starting_date'] : null;
            $expired_date = isset($_POST['expired_date']) && !empty($_POST['expired_date']) ? $_POST['expired_date'] : null;
        }
        
        $statuss = isset($_POST['statuss']) ? trim($_POST['statuss']) : 'Active';
        
        // Validate required fields
        if (empty($equipment_name)) {
            $_SESSION['error_message'] = "Equipment name is required.";
            header("Location: equipment.php");
            exit();
        } elseif ($category_id <= 0) {
            $_SESSION['error_message'] = "Please select a valid category.";
            header("Location: equipment.php");
            exit();
        } elseif ($equipment_location_id <= 0) {
            $_SESSION['error_message'] = "Please select a valid location.";
            header("Location: equipment.php");
            exit();
        } elseif (!empty($serial_number)) {
            // Check for duplicate serial number (excluding current equipment)
            $duplicate_check_query = "SELECT id FROM equipment WHERE serial_number = ? AND id != ?";
            $stmt_check = mysqli_prepare($conn, $duplicate_check_query);
            if ($stmt_check) {
                mysqli_stmt_bind_param($stmt_check, "si", $serial_number, $equipment_id);
                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                
                if (mysqli_num_rows($result_check) > 0) {
                    $_SESSION['error_message'] = "An equipment with this serial number already exists.";
                    header("Location: equipment.php");
                    exit();
                }
                mysqli_stmt_close($stmt_check);
            }
        }

        // Handle file upload for equipment image
        $equipment_image = '';
        $update_image = false;
        
        if (isset($_FILES['equipment_image']) && $_FILES['equipment_image']['size'] > 0) {
            $file = $_FILES['equipment_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error_message = "Invalid file type. Please upload an image file (JPG, PNG, GIF, WebP).";
            } elseif ($file['size'] > $max_size) {
                $error_message = "File size exceeds 5MB limit.";
            } else {
                // Create uploads directory if it doesn't exist
                if (!is_dir('../uploads/equipment')) {
                    mkdir('../uploads/equipment', 0755, true);
                }
                
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $equipment_image = 'equipment_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = '../uploads/equipment/' . $equipment_image;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $update_image = true;
                    
                    // Delete old image
                    $get_old_image_query = "SELECT equipment_image FROM equipment WHERE id = ?";
                    $stmt_old = mysqli_prepare($conn, $get_old_image_query);
                    if ($stmt_old) {
                        mysqli_stmt_bind_param($stmt_old, "i", $equipment_id);
                        mysqli_stmt_execute($stmt_old);
                        $result_old = mysqli_stmt_get_result($stmt_old);
                        if ($row_old = mysqli_fetch_assoc($result_old)) {
                            if (!empty($row_old['equipment_image'])) {
                                $old_image_path = '../uploads/equipment/' . $row_old['equipment_image'];
                                if (file_exists($old_image_path)) {
                                    unlink($old_image_path);
                                }
                            }
                        }
                        mysqli_stmt_close($stmt_old);
                    }
                } else {
                    $error_message = "Failed to upload image. Please try again.";
                }
            }
        }
        
        // Update database
        if (empty($error_message)) {
            if ($update_image) {
                $update_query = "UPDATE equipment SET equipment_name=?, equipment_image=?, category_id=?, serial_number=?, 
                                equipment_location_id=?, purchase_date=?, starting_date=?, expired_date=?, statuss=?, updated_at=NOW() 
                                WHERE id=?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ssisssdssi", $equipment_name, $equipment_image, $category_id, $serial_number, 
                                      $equipment_location_id, $purchase_date, $starting_date, $expired_date, $statuss, $equipment_id);
            } else {
                $update_query = "UPDATE equipment SET equipment_name=?, category_id=?, serial_number=?, 
                                equipment_location_id=?, purchase_date=?, starting_date=?, expired_date=?, statuss=?, updated_at=NOW() 
                                WHERE id=?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sisssdssi", $equipment_name, $category_id, $serial_number, 
                                      $equipment_location_id, $purchase_date, $starting_date, $expired_date, $statuss, $equipment_id);
            }
            
            if ($stmt && mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['success_message'] = "Equipment updated successfully!";
                header("Location: equipment.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error updating equipment: " . mysqli_error($conn);
                header("Location: equipment.php");
                exit();
            }
        }
    }
}

// Handle Delete Equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_equipment') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid request. Please try again.";
    } else {
        $equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
        
        if ($equipment_id > 0) {
            // Get equipment image for deletion
            $get_image_query = "SELECT equipment_image FROM equipment WHERE id = ?";
            $stmt = mysqli_prepare($conn, $get_image_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $equipment_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($result)) {
                    // Delete image file if exists
                    if (!empty($row['equipment_image'])) {
                        $image_path = '../uploads/equipment/' . $row['equipment_image'];
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
            
            // Delete equipment record
            $delete_query = "DELETE FROM equipment WHERE id = ?";
            $stmt = mysqli_prepare($conn, $delete_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $equipment_id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    // Store success message in session and redirect to prevent duplicate submissions on refresh
                    $_SESSION['success_message'] = "Equipment deleted successfully!";
                    header("Location: equipment.php");
                    exit();
                } else {
                    $error_message = "Error deleting equipment: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Fetch all equipment
$equipment_query = "SELECT e.id, e.equipment_name, e.equipment_image, e.serial_number, e.statuss,
                    e.category_id, e.equipment_location_id, e.purchase_date, e.starting_date, e.expired_date,
                    c.category_name, l.location_name, e.created_at
                    FROM equipment e
                    LEFT JOIN category c ON e.category_id = c.category_id
                    LEFT JOIN equipment_location l ON e.equipment_location_id = l.location_id
                    ORDER BY e.created_at DESC";
$equipment_result = mysqli_query($conn, $equipment_query);
if ($equipment_result) {
    while ($row = mysqli_fetch_assoc($equipment_result)) {
        $equipment_list[] = $row;
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .equipment-container {
            padding: 20px;
            background: #f5f7fa;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .equipment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }

        .equipment-header h2 {
            margin: 0;
            color: #1f2937;
            font-size: 24px;
        }
 
        .btn-add {
            background: #740101;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn-add:hover {
            background: #be4747;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
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
        }

        .modal.show {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            width: 95%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            color: #1e293b;
            font-size: 28px;
            font-weight: 700;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 32px;
            cursor: pointer;
            color: #94a3b8;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-btn:hover {
            color: #1e293b;
            background: #f1f5f9;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #94a3b8;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .required {
            color: #ef4444;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid #e2e8f0;
        }

        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
            transform: translateY(-2px);
        }

        /* Form Layout Styling */
        #equipmentForm {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        #equipmentForm .form-group:nth-child(1),
        #equipmentForm .form-group:nth-child(2),
        #equipmentForm .form-group:nth-child(3),
        #equipmentForm .form-group:nth-child(4),
        #equipmentForm .form-group:nth-child(5),
        #equipmentForm .form-group:nth-child(6) {
            margin-bottom: 0;
        }

        #equipmentForm .form-group:nth-child(7),
        #equipmentForm .form-group:nth-child(8) {
            grid-column: 1 / -1;
            margin-bottom: 0;
        }

        #equipmentForm .form-group:nth-child(9),
        #equipmentForm .form-group:nth-child(10) {
            margin-bottom: 0;
        }

        .warranty-fields {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .warranty-fields .form-group {
            margin-bottom: 0;
        }

        .equipment-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .equipment-img {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fecaca;
            color: #991b1b;
        }

        .badge-maintenance {
            background: #fed7aa;
            color: #92400e;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .btn-view {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-view:hover {
            background: #bfdbfe;
            transform: scale(1.05);
        }

        .btn-edit {
            background: #fef3c7;
            color: #d97706;
        }

        .btn-edit:hover {
            background: #fde68a;
            transform: scale(1.05);
        }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #fecaca;
            transform: scale(1.05);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 10px;
            background: #f3f4f6;
            border: 2px dashed #d1d5db;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            color: #6b7280;
            transition: all 0.3s;
        }

        .file-input-label:hover {
            background: #f9fafb;
            border-color: #2563eb;
            color: #2563eb;
        }

        .file-name {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .image-preview-container {
            display: none;
            margin-top: 16px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .image-preview-container.show {
            display: block;
        }

        .preview-image-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
        }

        .preview-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            width: 100%;
        }

        .preview-button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-change-image {
            background: #2563eb;
            color: white;
        }

        .btn-change-image:hover {
            background: #1d4ed8;
        }

        .btn-remove-image {
            background: #ef4444;
            color: white;
        }

        .btn-remove-image:hover {
            background: #dc2626;
        }

        @media (max-width: 1024px) {
            .modal-content {
                max-width: 90%;
            }

            #equipmentForm {
                grid-template-columns: 1fr;
            }

            #equipmentForm .form-group:nth-child(7),
            #equipmentForm .form-group:nth-child(8) {
                grid-column: 1;
            }

            .warranty-fields {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }
        }

        @media (max-width: 768px) {
            .modal-content {
                padding: 25px;
                max-width: 95%;
            }

            .modal-header {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }

            .modal-header h3 {
                font-size: 22px;
            }

            #equipmentForm {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .warranty-fields {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .equipment-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-add {
                width: 100%;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include './include/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <?php include './include/header.php'; ?>

        <!-- Equipment Content -->
        <div class="dashboard-content">
            <div class="equipment-container">
                <!-- Header with Add Button -->
                <div class="equipment-header">
                    <h2><i class="fas fa-laptop-medical"></i> Equipment Management</h2>
                    <button class="btn-add" id="addEquipmentBtn">
                        <i class="fas fa-plus"></i> Add Equipment
                    </button>
                </div>

                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <?php endif; ?>

                <!-- Equipment Table -->
                <div class="equipment-table">
                    <div class="table-wrapper">
                        <?php if (count($equipment_list) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Equipment Name</th>
                                    <th>Category</th>
                                    <th>Serial Number</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Purchase Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment_list as $equipment): ?>
                                <tr>
                                    <td>
                                        <?php
                                            $img_src = (!empty($equipment['equipment_image']) && file_exists('../uploads/equipment/' . $equipment['equipment_image']))
                                                ? '../uploads/equipment/' . htmlspecialchars($equipment['equipment_image'])
                                                : '../static/images/default-equipment.png';
                                        ?>
                                        <img src="<?php echo $img_src; ?>" alt="Equipment" class="equipment-img">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($equipment['equipment_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($equipment['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['serial_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['location_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                            $status_class = 'badge-' . strtolower($equipment['statuss']);
                                            if (!in_array($equipment['statuss'], ['Active', 'Inactive', 'Maintenance'])) {
                                                $status_class = 'badge-active';
                                            }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($equipment['statuss']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($equipment['purchase_date']) ? date('M d, Y', strtotime($equipment['purchase_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="equipment-details.php?id=<?php echo $equipment['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn-icon btn-edit" onclick="editEquipment(<?php echo $equipment['id']; ?>)" title="Edit Equipment">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this equipment?');">
                                                <input type="hidden" name="action" value="delete_equipment">
                                                <input type="hidden" name="equipment_id" value="<?php echo $equipment['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="submit" class="btn-icon btn-delete" title="Delete Equipment">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Equipment Found</h3>
                            <p>Click the "Add Equipment" button to add your first equipment.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Equipment Modal -->
    <div id="addEquipmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Equipment</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="equipmentForm">
                <input type="hidden" name="action" id="formAction" value="add_equipment">
                <input type="hidden" name="equipment_id" id="equipment_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="equipment_name">Equipment Name <span class="required">*</span></label>
                    <input type="text" id="equipment_name" name="equipment_name" placeholder="Enter equipment name" required>
                </div>

                <div class="form-group">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="serial_number">Serial Number</label>
                    <input type="text" id="serial_number" name="serial_number" placeholder="Enter serial number">
                </div>

                <div class="form-group">
                    <label for="equipment_location_id">Location <span class="required">*</span></label>
                    <select id="equipment_location_id" name="equipment_location_id" required>
                        <option value="">-- Select Location --</option>
                        <?php foreach ($locations as $location): ?>
                        <option value="<?php echo $location['location_id']; ?>">
                            <?php echo htmlspecialchars($location['location_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="purchase_date">Purchase Date</label>
                    <input type="date" id="purchase_date" name="purchase_date">
                </div>

                <div class="form-group">
                    <label>Does this equipment have a warranty? <span class="required">*</span></label>
                    <div style="display: flex; gap: 20px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin: 0; font-weight: normal;">
                            <input type="radio" name="has_warranty" value="yes" id="warranty_yes"> Yes
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin: 0; font-weight: normal;">
                            <input type="radio" name="has_warranty" value="no" id="warranty_no" checked> No
                        </label>
                    </div>
                </div>

                <div class="warranty-fields" id="warranty_fields" style="display: none;">
                    <div class="form-group">
                        <label for="starting_date">Warranty Starting Date</label>
                        <input type="date" id="starting_date" name="starting_date">
                    </div>

                    <div class="form-group">
                        <label for="expired_date">Warranty Expiration Date</label>
                        <input type="date" id="expired_date" name="expired_date">
                    </div>
                </div>

                <div class="form-group">
                    <label for="statuss">Status</label>
                    <select id="statuss" name="statuss">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="equipment_image">Equipment Image</label>
                    <div class="file-input-wrapper">
                        <label for="equipment_image" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div>Click to upload or drag and drop</div>
                            <div class="file-name">(JPG, PNG, GIF or WebP - max 5MB)</div>
                        </label>
                        <input type="file" id="equipment_image" name="equipment_image" accept="image/*">
                    </div>
                    <div id="fileName" class="file-name" style="margin-top: 8px;"></div>
                    
                    <!-- Image Preview Container -->
                    <div id="imagePreviewContainer" class="image-preview-container">
                        <div class="preview-image-wrapper">
                            <img id="previewImage" class="preview-image" alt="Preview">
                            <div class="preview-actions">
                                <button type="button" class="preview-button btn-change-image" onclick="document.getElementById('equipment_image').click()">
                                    <i class="fas fa-sync-alt"></i> Change Image
                                </button>
                                <button type="button" class="preview-button btn-remove-image" onclick="removeImagePreview()">
                                    <i class="fas fa-trash"></i> Remove Image
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Equipment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Equipment Modal -->
    <div id="viewEquipmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Equipment Details</h3>
                <button class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
<script src="./assets/js/script.js"></script>
    <script>
        // Get modal elements
        const addEquipmentModal = document.getElementById('addEquipmentModal');
        const viewEquipmentModal = document.getElementById('viewEquipmentModal');
        const addEquipmentBtn = document.getElementById('addEquipmentBtn');
        const equipmentForm = document.getElementById('equipmentForm');
        const fileInput = document.getElementById('equipment_image');
        const fileName = document.getElementById('fileName');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const previewImage = document.getElementById('previewImage');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const equipmentIdInput = document.getElementById('equipment_id');
        const submitBtn = document.getElementById('submitBtn');
        
        // Warranty fields
        const warrantyYes = document.getElementById('warranty_yes');
        const warrantyNo = document.getElementById('warranty_no');
        const warrantyFields = document.getElementById('warranty_fields');
        const startingDateInput = document.getElementById('starting_date');
        const expiredDateInput = document.getElementById('expired_date');

        // Handle warranty selection
        warrantyYes.addEventListener('change', function() {
            if (this.checked) {
                warrantyFields.style.display = 'block';
            }
        });

        warrantyNo.addEventListener('change', function() {
            if (this.checked) {
                warrantyFields.style.display = 'none';
                startingDateInput.value = '';
                expiredDateInput.value = '';
            }
        });

        // Open Add Equipment Modal
        addEquipmentBtn.addEventListener('click', function() {
            equipmentForm.reset();
            fileName.textContent = '';
            warrantyNo.checked = true;
            warrantyFields.style.display = 'none';
            imagePreviewContainer.classList.remove('show');
            
            // Reset modal for Add mode
            modalTitle.textContent = 'Add New Equipment';
            formAction.value = 'add_equipment';
            equipmentIdInput.value = '';
            submitBtn.textContent = 'Add Equipment';
            
            addEquipmentModal.classList.add('show');
        });

        // Handle file input change for image preview
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    imagePreviewContainer.classList.add('show');
                    fileName.textContent = 'Selected: ' + file.name;
                };
                
                reader.readAsDataURL(file);
            }
        });

        // Function to remove image preview
        function removeImagePreview() {
            fileInput.value = '';
            fileName.textContent = '';
            imagePreviewContainer.classList.remove('show');
        }

        // Close Add Equipment Modal
        function closeModal() {
            addEquipmentModal.classList.remove('show');
        }

        // Close View Equipment Modal
        function closeViewModal() {
            viewEquipmentModal.classList.remove('show');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === addEquipmentModal) {
                closeModal();
            }
            if (event.target === viewEquipmentModal) {
                closeViewModal();
            }
        });

        // Handle drag and drop
        const fileInputLabel = document.querySelector('.file-input-label');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileInputLabel.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileInputLabel.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileInputLabel.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            fileInputLabel.style.borderColor = '#2563eb';
            fileInputLabel.style.background = '#f0f4ff';
        }

        function unhighlight(e) {
            fileInputLabel.style.borderColor = '#d1d5db';
            fileInputLabel.style.background = '#f3f4f6';
        }

        fileInputLabel.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files && files[0]) {
                const file = files[0];
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPG, PNG, GIF, or WebP).');
                    return;
                }
                
                // Set the file input
                fileInput.files = files;
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    imagePreviewContainer.classList.add('show');
                    fileName.textContent = 'Selected: ' + file.name;
                };
                reader.readAsDataURL(file);
            }
        }

        // View Equipment Details
        function viewEquipment(id, name, serial, category, location, status, purchaseDate) {
            const content = `
                <div class="form-group">
                    <label>Equipment Name:</label>
                    <p style="padding: 10px; background: #f9fafb; border-radius: 6px; color: #374151; margin: 0;">${escapeHtml(name)}</p>
                </div>
                <div class="form-group">
                    <label>Serial Number:</label>
                    <p style="padding: 10px; background: #f9fafb; border-radius: 6px; color: #374151; margin: 0;">${serial || 'N/A'}</p>
                </div>
                <div class="form-group">
                    <label>Category:</label>
                    <p style="padding: 10px; background: #f9fafb; border-radius: 6px; color: #374151; margin: 0;">${category || 'N/A'}</p>
                </div>
                <div class="form-group">
                    <label>Location:</label>
                    <p style="padding: 10px; background: #f9fafb; border-radius: 6px; color: #374151; margin: 0;">${location || 'N/A'}</p>
                </div>
                <div class="form-group">
                    <label>Status:</label>
                    <p style="padding: 10px; background: #f9fafb; border-radius: 6px; color: #374151; margin: 0;">${escapeHtml(status)}</p>
                </div>
                <div class="form-group">
                    <label>Purchase Date:</label>
                    <p style="padding: 10px; background: #f9fafb; border-radius: 6px; color: #374151; margin: 0;">${purchaseDate ? new Date(purchaseDate).toLocaleDateString() : 'N/A'}</p>
                </div>
            `;
            
            document.getElementById('viewContent').innerHTML = content;
            viewEquipmentModal.classList.add('show');
        }

        // Edit Equipment
        function editEquipment(id) {
            // Show loading state or similar if desired
            
            // Fetch equipment details
            fetch('equipment.php?action=get_equipment_details&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const equipment = data.data;
                        
                        // Populate form fields
                        document.getElementById('equipment_name').value = equipment.equipment_name;
                        document.getElementById('category_id').value = equipment.category_id;
                        document.getElementById('serial_number').value = equipment.serial_number || '';
                        document.getElementById('equipment_location_id').value = equipment.equipment_location_id;
                        document.getElementById('purchase_date').value = equipment.purchase_date || '';
                        document.getElementById('statuss').value = equipment.statuss;
                        
                        // Handle warranty
                        if (equipment.starting_date && equipment.expired_date) {
                            warrantyYes.checked = true;
                            warrantyFields.style.display = 'block';
                            document.getElementById('starting_date').value = equipment.starting_date;
                            document.getElementById('expired_date').value = equipment.expired_date;
                        } else {
                            warrantyNo.checked = true;
                            warrantyFields.style.display = 'none';
                            document.getElementById('starting_date').value = '';
                            document.getElementById('expired_date').value = '';
                        }
                        
                        // Handle image preview
                        if (equipment.equipment_image) {
                            previewImage.src = '../uploads/equipment/' + equipment.equipment_image;
                            imagePreviewContainer.classList.add('show');
                            fileName.textContent = 'Current Image: ' + equipment.equipment_image;
                        } else {
                            imagePreviewContainer.classList.remove('show');
                            fileName.textContent = '';
                        }
                        
                        // Set modal for Edit mode
                        modalTitle.textContent = 'Edit Equipment';
                        formAction.value = 'update_equipment';
                        equipmentIdInput.value = equipment.id;
                        submitBtn.textContent = 'Update Equipment';
                        
                        // Show modal
                        addEquipmentModal.classList.add('show');
                    } else {
                        alert('Error fetching equipment details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching equipment details.');
                });
        }

        // Escape HTML function for security
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Form validation and Loading State
        equipmentForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            
            // Prevent double submission
            if (submitBtn.disabled) {
                e.preventDefault();
                return;
            }

            const equipmentName = document.getElementById('equipment_name').value.trim();
            const categoryId = document.getElementById('category_id').value;
            const locationId = document.getElementById('equipment_location_id').value;

            if (!equipmentName) {
                e.preventDefault();
                alert('Equipment name is required');
                return;
            }

            if (!categoryId) {
                e.preventDefault();
                alert('Please select a category');
                return;
            }

            if (!locationId) {
                e.preventDefault();
                alert('Please select a location');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            submitBtn.style.cursor = 'not-allowed';
            const originalText = submitBtn.textContent; // Store original text if needed
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });

        // Handle Delete Forms Loading State
        document.querySelectorAll('.btn-delete').forEach(btn => {
            const form = btn.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (btn.disabled) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Disable button and show loading
                    btn.disabled = true;
                    btn.style.opacity = '0.7';
                    btn.style.cursor = 'not-allowed';
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                });
            }
        });
    </script>
</body>
</html>
