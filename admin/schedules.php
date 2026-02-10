<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Check if user has admin role
$user_role = isset($_SESSION['user_role']) ? trim($_SESSION['user_role']) : null;
$is_admin = ($user_role === 'admin');

// If user is not admin, redirect them
if (!$is_admin) {
    header("Location: ./dashboard.php?error=" . urlencode("You do not have permission to access this page"));
    exit();
}

// Include database connection
include('../config/db.php');
require_once('../lib/mailer.php');

// Generate CSRF token if not exists
if (empty($_SESSION['schedule_token'])) {
    $_SESSION['schedule_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$success_message = '';
$error_message = '';
$schedules = [];

// Check if redirect came from successful insert
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Maintenance schedule added successfully!";
    if (isset($_GET['warning'])) {
        $success_message .= " (Warning: Email notification failed: " . htmlspecialchars($_GET['warning']) . ")";
    }
}

// Check if redirect came from successful edit
if (isset($_GET['success']) && $_GET['success'] == '2') {
    $success_message = "Maintenance schedule updated successfully!";
}

// Check if redirect came from successful delete
if (isset($_GET['success']) && $_GET['success'] == '3') {
    $success_message = "Maintenance schedule deleted successfully!";
}

// Handle Add Schedule
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['schedule_token']) {
        $error_message = "Security token validation failed. Please try again.";
    } else {
        $equipment_ids = isset($_POST['equipment_id']) ? $_POST['equipment_id'] : [];
        if (!is_array($equipment_ids)) {
            $equipment_ids = [$equipment_ids];
        }
        $maintenance_type = trim($_POST['maintenance_type']);
        $interval_value = intval($_POST['interval_value']);
        $interval_unit = trim($_POST['interval_unit']);
        $start_date = trim($_POST['start_date']);
        $assigned_to_user_id = intval($_POST['assigned_to_user_id']);
        
        if (!empty($equipment_ids) && !empty($maintenance_type) && $interval_value > 0 && !empty($interval_unit) && !empty($start_date) && !empty($assigned_to_user_id)) {
            // Validate interval_unit
            $valid_units = ['SECOND', 'MINUTE', 'DAY', 'MONTH', 'YEAR'];
            if (!in_array($interval_unit, $valid_units)) {
                $error_message = "Invalid interval unit selected.";
            } else {
                $inserted_count = 0;
                $assigned_equipment_names = [];
                $error_occurred = false;

                // Fetch Technician Details once
                $tech_email = '';
                $tech_name = '';
                $tech_sql = "SELECT email, firstname, lastname FROM users WHERE user_id = ?";
                $tech_stmt = $conn->prepare($tech_sql);
                if ($tech_stmt) {
                    $tech_stmt->bind_param("i", $assigned_to_user_id);
                    $tech_stmt->execute();
                    $tech_result = $tech_stmt->get_result();
                    if ($tech_data = $tech_result->fetch_assoc()) {
                        $tech_email = $tech_data['email'];
                        $tech_name = $tech_data['firstname'] . ' ' . $tech_data['lastname'];
                    }
                    $tech_stmt->close();
                }

                $sql = "INSERT INTO maintenance_schedule (equipment_id, maintenance_type, interval_value, interval_unit, start_date, assigned_to_user_id) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                foreach ($equipment_ids as $eq_id) {
                    $eq_id = intval($eq_id);
                    if ($eq_id <= 0) continue;

                    $stmt->bind_param("isissi", $eq_id, $maintenance_type, $interval_value, $interval_unit, $start_date, $assigned_to_user_id);
                    
                    if ($stmt->execute()) {
                        $inserted_count++;
                        
                        // Fetch Equipment Name
                        $eq_name_sql = "SELECT equipment_name FROM equipment WHERE id = ?";
                        $eq_name_stmt = $conn->prepare($eq_name_sql);
                        if ($eq_name_stmt) {
                            $eq_name_stmt->bind_param("i", $eq_id);
                            $eq_name_stmt->execute();
                            $eq_name_result = $eq_name_stmt->get_result();
                            if ($eq_row = $eq_name_result->fetch_assoc()) {
                                $assigned_equipment_names[] = $eq_row['equipment_name'];
                                
                                // Notification for each equipment (or we could batch, but separate notifications are fine for history)
                                $notif_msg = "New maintenance schedule assigned for " . $eq_row['equipment_name'];
                                $notif_type = "schedule";
                                $notif_sql = "INSERT INTO notification (user_id, message, type, is_read) VALUES (?, ?, ?, 0)";
                                $notif_stmt = $conn->prepare($notif_sql);
                                if ($notif_stmt) {
                                    $notif_stmt->bind_param("iss", $assigned_to_user_id, $notif_msg, $notif_type);
                                    $notif_stmt->execute();
                                    $notif_stmt->close();
                                }
                            }
                            $eq_name_stmt->close();
                        }
                    } else {
                        $error_occurred = true;
                        $error_message = "Error adding schedule for equipment ID $eq_id: " . $conn->error;
                    }
                }
                $stmt->close();

                if ($inserted_count > 0) {
                    // Send One Email with List
                    if (!empty($tech_email)) {
                        $subject = 'New Maintenance Schedules Assigned - Action Required';
                        
                        $equipment_list_html = "";
                        foreach ($assigned_equipment_names as $name) {
                            $equipment_list_html .= "<li style='margin-bottom: 5px; color: #1e293b;'><strong>" . htmlspecialchars($name) . "</strong></li>";
                        }

                        $htmlBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; background-color: #ffffff;'>
                            <div style='text-align: center; margin-bottom: 20px;'>
                                <img src='cid:company_logo' alt='Company Logo' style='max-width: 150px; height: auto;'>
                            </div>
                            <div style='background-color: #f0f9ff; padding: 15px; border-left: 4px solid #0284c7; margin-bottom: 25px; border-radius: 0 4px 4px 0;'>
                                <h2 style='margin: 0; color: #0c4a6e; font-size: 20px;'>New Maintenance Assignments</h2>
                            </div>
                            <p style='color: #334155; font-size: 16px; line-height: 1.5;'>Dear <strong>" . htmlspecialchars($tech_name) . "</strong>,</p>
                            <p style='color: #334155; font-size: 16px; line-height: 1.5;'>You have been assigned new maintenance schedules for the following equipment:</p>
                            
                            <ul style='background-color: #f8fafc; padding: 15px 15px 15px 35px; border-radius: 8px; margin: 20px 0;'>
                                $equipment_list_html
                            </ul>

                            <table style='width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 15px;'>
                                <tr style='background-color: #f8fafc;'>
                                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #475569; width: 40%;'>Maintenance Type:</td>
                                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b;'>" . htmlspecialchars($maintenance_type) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #475569;'>Frequency:</td>
                                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b;'>Every " . htmlspecialchars($interval_value) . " " . htmlspecialchars($interval_unit) . "</td>
                                </tr>
                                <tr style='background-color: #f8fafc;'>
                                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #475569;'>Start Date:</td>
                                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b;'>" . htmlspecialchars($start_date) . "</td>
                                </tr>
                            </table>
                            
                            <p style='color: #334155; font-size: 16px; line-height: 1.5;'>Please log into the Technician Dashboard to view more details and update the status once the tasks are initiated.</p>
                            
                            <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; text-align: center;'>
                                <p>This is an automated notification from the Equipment Management System.</p>
                                <p>&copy; " . date('Y') . " Equipment Management System. All rights reserved.</p>
                            </div>
                        </div>
                        ";
                        $mailResult = send_app_mail($tech_email, $tech_name, $subject, $htmlBody);
                    }

                    // Regenerate token after successful submission
                    $_SESSION['schedule_token'] = bin2hex(random_bytes(32));
                    // Redirect to prevent duplicate submission on refresh
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1" . (isset($mailResult) && !$mailResult['success'] ? "&warning=" . urlencode($mailResult['error']) : ""));
                    exit();
                } else {
                    if (!$error_occurred) {
                        $error_message = "No equipment selected or invalid IDs.";
                    }
                }
            }
        } else {
            $error_message = "All fields are required!";
        }
    }
}

// Handle Delete Schedule
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $schedule_id = intval($_POST['schedule_id']);
    
    $sql = "DELETE FROM maintenance_schedule WHERE schedule_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $schedule_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=3");
        exit();
    } else {
        $error_message = "Error deleting schedule: " . $conn->error;
    }
    $stmt->close();
}

// Handle Edit Schedule
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['schedule_token']) {
        $error_message = "Security token validation failed. Please try again.";
    } else {
        $schedule_id = intval($_POST['schedule_id']);
        $equipment_ids = isset($_POST['equipment_id']) ? $_POST['equipment_id'] : [];
        if (!is_array($equipment_ids)) {
            $equipment_ids = [$equipment_ids];
        }
        $maintenance_type = trim($_POST['maintenance_type']);
        $interval_value = intval($_POST['interval_value']);
        $interval_unit = trim($_POST['interval_unit']);
        $start_date = trim($_POST['start_date']);
        $assigned_to_user_id = intval($_POST['assigned_to_user_id']);
        
        if (!empty($equipment_ids) && !empty($maintenance_type) && $interval_value > 0 && !empty($interval_unit) && !empty($start_date) && !empty($assigned_to_user_id)) {
            // Validate interval_unit
            $valid_units = ['SECOND', 'MINUTE', 'DAY', 'MONTH', 'YEAR'];
            if (!in_array($interval_unit, $valid_units)) {
                $error_message = "Invalid interval unit selected.";
            } else {
                // Update the existing schedule with the FIRST selected equipment
                $first_eq_id = intval(array_shift($equipment_ids));
                
                $sql = "UPDATE maintenance_schedule SET equipment_id = ?, maintenance_type = ?, interval_value = ?, interval_unit = ?, start_date = ?, assigned_to_user_id = ? WHERE schedule_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isissii", $first_eq_id, $maintenance_type, $interval_value, $interval_unit, $start_date, $assigned_to_user_id, $schedule_id);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    
                    // If there are MORE equipment selected, INSERT new schedules for them
                    if (!empty($equipment_ids)) {
                        $insert_sql = "INSERT INTO maintenance_schedule (equipment_id, maintenance_type, interval_value, interval_unit, start_date, assigned_to_user_id) 
                                       VALUES (?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_sql);
                        
                        foreach ($equipment_ids as $next_eq_id) {
                            $next_eq_id = intval($next_eq_id);
                            if ($next_eq_id <= 0) continue;
                            
                            $insert_stmt->bind_param("isissi", $next_eq_id, $maintenance_type, $interval_value, $interval_unit, $start_date, $assigned_to_user_id);
                            $insert_stmt->execute();
                            
                            // Send notification for new assignments (simplified, reusing logic could be better but this is quick)
                            $notif_msg = "New maintenance schedule assigned (via edit)";
                            $notif_type = "schedule";
                            $notif_sql = "INSERT INTO notification (user_id, message, type, is_read) VALUES (?, ?, ?, 0)";
                            $notif_stmt_ins = $conn->prepare($notif_sql);
                            if ($notif_stmt_ins) {
                                $notif_stmt_ins->bind_param("iss", $assigned_to_user_id, $notif_msg, $notif_type);
                                $notif_stmt_ins->execute();
                                $notif_stmt_ins->close();
                            }
                        }
                        $insert_stmt->close();
                    }

                    // Regenerate token after successful submission
                    $_SESSION['schedule_token'] = bin2hex(random_bytes(32));
                    // Redirect to prevent duplicate submission on refresh
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=2");
                    exit();
                } else {
                    $error_message = "Error updating schedule: " . $conn->error;
                }
            }
        } else {
            $error_message = "All fields are required!";
        }
    }
}

// Fetch all equipment
$equipment_list = [];
$sql = "SELECT id, equipment_name FROM equipment ORDER BY equipment_name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipment_list[] = $row;
    }
}

// Fetch all technician users for assignment
$users_list = [];
$sql = "SELECT u.user_id, u.firstname, u.lastname 
        FROM users u
        JOIN role r ON u.role_id = r.role_id
        WHERE u.statuss = '1' AND r.role_name = 'technician' 
        ORDER BY u.firstname ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users_list[] = $row;
    }
}

// Fetch all schedules with related data
$sql = "SELECT 
            ms.schedule_id,
            ms.equipment_id,
            ms.maintenance_type,
            ms.interval_value,
            ms.interval_unit,
            ms.start_date,
            ms.assigned_to_user_id,
            ms.created_at,
            ms.updated_at,
            e.equipment_name,
            u.firstname,
            u.lastname
        FROM maintenance_schedule ms
        LEFT JOIN equipment e ON ms.equipment_id = e.id
        LEFT JOIN users u ON ms.assigned_to_user_id = u.user_id
        ORDER BY ms.created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Schedules - Equipment Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .schedules-form {
            background: var(--white);
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-submit {
            background: #7f1d1d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
        }

        .btn-submit:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .modal-form .btn-submit {
            padding: 13px 28px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .modal-form .btn-submit:hover {
            background: #991b1b;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(127, 29, 29, 0.2);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 13px;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            border-radius: 6px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            width: 40px;
            height: 40px;
        }

        .btn-edit {
            color: white;
            background: #22c55e;
        }

        .btn-edit:hover {
            background: #16a34a;
            color: white;
        }

        .btn-delete {
            color: white;
            background: var(--danger);
        }

        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
            transition: opacity 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.fade-out {
            opacity: 0;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .schedules-table {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .schedules-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedules-table thead {
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }

        .schedules-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 14px;
        }

        .schedules-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-size: 14px;
        }

        .schedules-table tbody tr:hover {
            background: var(--gray-50);
        }

        .schedules-table tbody tr:last-child td {
            border-bottom: none;
        }

        .schedule-id {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .schedule-actions {
            display: flex;
            gap: 8px;
        }

        .created-date {
            font-size: 13px;
            color: var(--gray-500);
        }

        .interval-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--gray-300);
            margin-bottom: 16px;
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: flex-end;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .schedule-actions {
                flex-direction: column;
            }

            .btn-edit, .btn-danger {
                width: 100%;
            }

            .schedules-table {
                overflow-x: auto;
            }
        }

        .stats-mini {
            display: grid;
            grid-template-columns: 30%;
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat-mini-card {
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-mini-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: #f0f9ff;
            color: var(--primary-blue);
        }

        .stat-mini-content h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-800);
        }

        .stat-mini-content p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: var(--gray-600);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.4) 0%, rgba(0, 0, 0, 0.6) 100%);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 0;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 850px;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.5);
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background-color:#991b1b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 32px 40px;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 4px 20px rgba(30, 64, 175, 0.2);
        }

        .modal-header h2 {
            margin: 0;
            color: white;
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 14px;
            letter-spacing: -0.5px;
        }

        .modal-header i {
            font-size: 28px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-form {
            padding: 40px;
            flex: 1;
            overflow-y: auto;
        }

        .modal-form .form-group {
            margin-bottom: 28px;
        }

        .modal-form .form-group:last-of-type {
            margin-bottom: 0;
        }

        .modal-form .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 700;
            color: #1e293b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .modal-form .form-group input,
        .modal-form .form-group select,
        .modal-form .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: white;
            color: #334155;
        }

        .modal-form .form-group input::placeholder,
        .modal-form .form-group select::placeholder {
            color: #cbd5e1;
        }

        .modal-form .form-group input:hover,
        .modal-form .form-group select:hover,
        .modal-form .form-group textarea:hover {
            border-color: #cbd5e1;
        }

        .modal-form .form-group input:focus,
        .modal-form .form-group select:focus,
        .modal-form .form-group textarea:focus {
            outline: none;
            border-color: #991b1b;
            background: white;
            box-shadow: 0 0 0 5px rgba(30, 64, 175, 0.08), 0 0 0 2px rgba(30, 64, 175, 0.2);
        }

        .modal-footer {
            display: flex;
            gap: 14px;
            justify-content: flex-end;
            padding: 24px 40px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-radius: 0 0 20px 20px;
            flex-shrink: 0;
        }

        .btn-cancel {
            background: white;
            color: #475569;
            padding: 12px 28px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-cancel:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .btn-cancel:active {
            transform: translateY(0);
        }

        .modal-form .btn-submit {
            background-color:#991b1b;
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(30, 64, 175, 0.2);
        }

        .modal-form .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(30, 64, 175, 0.3);
        }

        .modal-form .btn-submit:active {
            transform: translateY(-1px);
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .form-row-2 {
                grid-template-columns: 1fr;
            }

            .modal-header {
                padding: 24px 20px;
            }

            .modal-form {
                padding: 24px 20px;
            }

            .modal-footer {
                padding: 16px 20px;
                flex-direction: column-reverse;
            }

            .btn-cancel,
            .modal-form .btn-submit {
                width: 100%;
                justify-content: center;
            }
        }

        .modal-form .form-row-2 .form-group {
            margin-bottom: 28px;
        }
    </style>
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
            <!-- Messages -->
            <?php if (isset($success_message) && !empty($success_message)): ?>
                <div id="successAlert" class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Mini -->
            <div class="stats-mini">
                <div class="stat-mini-card">
                    <div class="stat-mini-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h3><?php echo count($schedules); ?></h3>
                        <p>Total Schedules</p>
                    </div>
                </div>
            </div>

            <!-- Add Schedule Button -->
            <?php if ($is_admin): ?>
            <div style="margin-bottom: 30px;">
                <button id="addScheduleBtn" class="btn-submit" style="margin: 0;">
                    <i class="fas fa-plus"></i> Add New Schedule
                </button>
            </div>
            <?php endif; ?>

            <!-- Add Schedule Modal -->
            <?php if ($is_admin): ?>
            <div id="addScheduleModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>
                            <i class="fas fa-plus-circle" style="color: var(--primary-blue); margin-right: 8px;"></i>
                            Add New Maintenance Schedule
                        </h2>
                        <button class="modal-close" id="closeModalBtn">&times;</button>
                    </div>

                    <form method="POST" action="" class="modal-form" id="addScheduleForm">
                        <div class="form-group">
                            <label for="modal_equipment_id">Equipment (Hold Ctrl/Cmd to select multiple) *</label>
                            <select id="modal_equipment_id" name="equipment_id[]" multiple required style="height: 120px;">
                                <?php foreach ($equipment_list as $eq): ?>
                                    <option value="<?php echo $eq['id']; ?>">
                                        <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="font-size: 12px; color: var(--gray-500); margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> You can select multiple equipment for the same schedule.
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="modal_maintenance_type">Maintenance Type *</label>
                            <input 
                                type="text" 
                                id="modal_maintenance_type" 
                                name="maintenance_type" 
                                placeholder="e.g., Regular Service, Oil Change, Inspection"
                                required
                            >
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="modal_interval_value">Interval Value *</label>
                                <input 
                                    type="number" 
                                    id="modal_interval_value" 
                                    name="interval_value" 
                                    placeholder="Enter number (e.g., 7, 30, 365)"
                                    min="1"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="modal_interval_unit">Interval Unit *</label>
                                <select id="modal_interval_unit" name="interval_unit" required>
                                    <option value="">-- Select Unit --</option>
                                    <option value="SECOND">Second</option>
                                    <option value="MINUTE">Minute</option>
                                    <option value="DAY">Day</option>
                                    <option value="MONTH">Month</option>
                                    <option value="YEAR">Year</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="modal_start_date">Start Date *</label>
                            <input 
                                type="date" 
                                id="modal_start_date" 
                                name="start_date" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="modal_assigned_to_user_id">Assign To *</label>
                            <select id="modal_assigned_to_user_id" name="assigned_to_user_id" required>
                                <option value="">-- Select User --</option>
                                <?php foreach ($users_list as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-plus"></i> Add Schedule
                            </button>
                        </div>

                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['schedule_token']); ?>">
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit Schedule Modal -->
            <?php if ($is_admin): ?>
            <div id="editScheduleModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>
                            <i class="fas fa-edit" style="color: var(--primary-blue); margin-right: 8px;"></i>
                            Edit Maintenance Schedule
                        </h2>
                        <button class="modal-close" id="closeEditModalBtn">&times;</button>
                    </div>

                    <form method="POST" action="" class="modal-form" id="editScheduleForm">
                        <div class="form-group">
                            <label for="edit_equipment_id">Equipment (Hold Ctrl/Cmd to select multiple) *</label>
                            <select id="edit_equipment_id" name="equipment_id[]" multiple required style="height: 120px;">
                                <?php foreach ($equipment_list as $eq): ?>
                                    <option value="<?php echo $eq['id']; ?>">
                                        <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="font-size: 12px; color: var(--gray-500); margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Selecting multiple will create new schedules for the additional equipment.
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="edit_maintenance_type">Maintenance Type *</label>
                            <input 
                                type="text" 
                                id="edit_maintenance_type" 
                                name="maintenance_type" 
                                placeholder="e.g., Regular Service, Oil Change, Inspection"
                                required
                            >
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="edit_interval_value">Interval Value *</label>
                                <input 
                                    type="number" 
                                    id="edit_interval_value" 
                                    name="interval_value" 
                                    placeholder="Enter number (e.g., 7, 30, 365)"
                                    min="1"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="edit_interval_unit">Interval Unit *</label>
                                <select id="edit_interval_unit" name="interval_unit" required>
                                    <option value="">-- Select Unit --</option>
                                    <option value="SECOND">Second</option>
                                    <option value="MINUTE">Minute</option>
                                    <option value="DAY">Day</option>
                                    <option value="MONTH">Month</option>
                                    <option value="YEAR">Year</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_start_date">Start Date *</label>
                            <input 
                                type="date" 
                                id="edit_start_date" 
                                name="start_date" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="edit_assigned_to_user_id">Assign To *</label>
                            <select id="edit_assigned_to_user_id" name="assigned_to_user_id" required>
                                <option value="">-- Select User --</option>
                                <?php foreach ($users_list as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Update Schedule
                            </button>
                        </div>

                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="schedule_id" id="edit_schedule_id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['schedule_token']); ?>">
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Schedules Table -->
            <div class="schedules-table">
                <?php if (count($schedules) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Equipment</th>
                                <th>Maintenance Type</th>
                                <th>Interval</th>
                                <th>Start Date</th>
                                <th>Assigned To</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($schedules as $schedule): 
                            ?>
                                <tr>
                                    <td class="schedule-id">#<?php echo $counter; ?></td>
                                    <td><?php echo htmlspecialchars($schedule['equipment_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['maintenance_type']); ?></td>
                                    <td>
                                        <span class="interval-badge">
                                            <?php echo $schedule['interval_value'] . ' ' . strtolower($schedule['interval_unit']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="created-date">
                                            <?php echo date('M d, Y', strtotime($schedule['start_date'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($schedule['firstname'] . ' ' . $schedule['lastname']); ?></td>
                                    <td>
                                        <span class="created-date">
                                            <?php echo date('M d, Y H:i', strtotime($schedule['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_admin): ?>
                                        <button type="button" class="btn-icon btn-edit" onclick="openEditModal(
                                            <?php echo $schedule['schedule_id']; ?>,
                                            <?php echo $schedule['equipment_id']; ?>,
                                            '<?php echo htmlspecialchars($schedule['maintenance_type'], ENT_QUOTES); ?>',
                                            <?php echo $schedule['interval_value']; ?>,
                                            '<?php echo htmlspecialchars($schedule['interval_unit'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars(date('Y-m-d', strtotime($schedule['start_date'])), ENT_QUOTES); ?>',
                                            <?php echo $schedule['assigned_to_user_id']; ?>
                                        );" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span style="color: var(--gray-500); font-size: 13px;">No permissions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                            $counter++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Schedules Yet</h3>
                        <p>Start by adding your first maintenance schedule above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="./assets/js/script.js"></script>
    <script>
        // Auto-hide success message after 5 seconds
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(function() {
                successAlert.classList.add('fade-out');
                setTimeout(function() {
                    successAlert.style.display = 'none';
                }, 300);
            }, 5000);
        }
    </script>
    <?php if ($is_admin): ?>
    <script>
        // Get modal elements
        const addScheduleModal = document.getElementById('addScheduleModal');
        const addScheduleBtn = document.getElementById('addScheduleBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const modalForm = addScheduleModal.querySelector('form');

        // Open modal
        addScheduleBtn.addEventListener('click', function() {
            addScheduleModal.classList.add('active');
            document.getElementById('modal_equipment_id').focus();
        });

        // Close modal
        function closeModal() {
            addScheduleModal.classList.remove('active');
            modalForm.reset();
        }

        closeModalBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        // Close modal when clicking outside of it
        addScheduleModal.addEventListener('click', function(event) {
            if (event.target === addScheduleModal) {
                closeModal();
            }
        });

        // Prevent modal close when clicking inside modal content
        addScheduleModal.querySelector('.modal-content').addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // ========== Edit Schedule Modal ==========
        const editScheduleModal = document.getElementById('editScheduleModal');
        const closeEditModalBtn = document.getElementById('closeEditModalBtn');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        const editModalForm = editScheduleModal.querySelector('form');

        // Open edit modal
        function openEditModal(scheduleId, equipmentId, maintenanceType, intervalValue, intervalUnit, startDate, assignedToUserId) {
            document.getElementById('edit_schedule_id').value = scheduleId;
            
            // Handle multi-select population
            const eqSelect = document.getElementById('edit_equipment_id');
            for (let i = 0; i < eqSelect.options.length; i++) {
                eqSelect.options[i].selected = false;
                if (eqSelect.options[i].value == equipmentId) {
                    eqSelect.options[i].selected = true;
                }
            }
            
            document.getElementById('edit_maintenance_type').value = maintenanceType;
            document.getElementById('edit_interval_value').value = intervalValue;
            document.getElementById('edit_interval_unit').value = intervalUnit;
            document.getElementById('edit_start_date').value = startDate;
            document.getElementById('edit_assigned_to_user_id').value = assignedToUserId;
            editScheduleModal.classList.add('active');
            document.getElementById('edit_equipment_id').focus();
        }

        // Close edit modal
        function closeEditModal() {
            editScheduleModal.classList.remove('active');
            editModalForm.reset();
        }

        closeEditModalBtn.addEventListener('click', closeEditModal);
        cancelEditBtn.addEventListener('click', closeEditModal);

        // Close modal when clicking outside of it
        editScheduleModal.addEventListener('click', function(event) {
            if (event.target === editScheduleModal) {
                closeEditModal();
            }
        });

        // Prevent modal close when clicking inside modal content
        editScheduleModal.querySelector('.modal-content').addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // Loading State for Add Schedule Form
        const addScheduleForm = document.getElementById('addScheduleForm');
        if (addScheduleForm) {
            addScheduleForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return;
                }
                
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            });
        }

        // Loading State for Edit Schedule Form
        const editScheduleForm = document.getElementById('editScheduleForm');
        if (editScheduleForm) {
            editScheduleForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return;
                }
                
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            });
        }

        // Loading State for Delete Forms
        document.querySelectorAll('form[onsubmit*="delete"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return;
                }
                submitBtn.disabled = true;
                // No text change for icon buttons, just disable
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
