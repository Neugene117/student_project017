<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=" . urlencode("Please log in first"));
    exit();
}

// Check if user has technician role
$user_role = isset($_SESSION['user_role']) ? trim($_SESSION['user_role']) : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$is_technician = ($user_role === 'technician');

// If user is not technician, redirect them
    if (!$is_technician) {
        header("Location: ./dashboard.php?error=" . urlencode("You do not have permission to access this page"));
        exit();
    }
    
    // Set default timezone for the script
    date_default_timezone_set('Africa/Kigali');
    
    // Include database connection
    include('../config/db.php');
require_once('../lib/mailer.php');

// Function to calculate next maintenance date
function getNextMaintenanceDate($start_date_str, $interval_value, $interval_unit, $last_maintenance_date_str = null) {
    // Set timezone to match your database or location
    date_default_timezone_set('Africa/Kigali'); // Adjust this to your timezone (e.g., Africa/Kigali)
    
    $start_date = new DateTime($start_date_str);
    $current_time = new DateTime('now');
    $interval_unit = strtoupper(trim($interval_unit));
    
    // Define interval in seconds
    $interval_seconds = [
        'SECOND' => 1,
        'MINUTE' => 60,
        'HOUR' => 3600,
        'DAY' => 86400,
        'MONTH' => 2592000,  // 30 days
        'YEAR' => 31536000   // 365 days
    ];
    
    $total_seconds = $interval_value * ($interval_seconds[$interval_unit] ?? 86400);
    
    $start_ts = $start_date->getTimestamp();
    $now_ts = $current_time->getTimestamp();
    
    // Determine the baseline for the next due date
    if ($last_maintenance_date_str) {
        // If maintenance was performed, the next due date is relative to that
        $last_maintenance = new DateTime($last_maintenance_date_str);
        $next_maintenance_ts = $last_maintenance->getTimestamp() + $total_seconds;
    } else {
        // If no maintenance ever performed
        if ($now_ts < $start_ts) {
            // If start date is in the future, that is the first due date
            $next_maintenance_ts = $start_ts;
        } else {
            // If start date is past and no maintenance done, it is due immediately (or remains due)
            // We set it to start_date so it appears overdue
            $next_maintenance_ts = $start_ts;
        }
    }
    
    $next_maintenance = new DateTime();
    $next_maintenance->setTimestamp($next_maintenance_ts);
    
    $seconds_until_due = $next_maintenance_ts - $now_ts;
    
    return [
        'next_date' => $next_maintenance,
        'is_due' => ($seconds_until_due <= 0),
        'seconds_until_due' => max(0, $seconds_until_due)
    ];
}

// Fetch technician name and email
$technician_name = "Unknown Technician";
$technician_email = "";
if ($user_id) {
    $tech_sql = "SELECT firstname, lastname, email FROM users WHERE user_id = ?";
    $tech_stmt = $conn->prepare($tech_sql);
    if ($tech_stmt) {
        $tech_stmt->bind_param("i", $user_id);
        $tech_stmt->execute();
        $tech_result = $tech_stmt->get_result();
        if ($tech_row = $tech_result->fetch_assoc()) {
            $technician_name = $tech_row['firstname'] . ' ' . $tech_row['lastname'];
            $technician_email = $tech_row['email'];
        }
        $tech_stmt->close();
    }
}

// Handle Due Alert AJAX
if (isset($_POST['trigger_due_alert']) && isset($_POST['schedule_id'])) {
    header('Content-Type: application/json');
    $schedule_id = intval($_POST['schedule_id']);
    
    // Fetch schedule details
    $sch_sql = "SELECT * FROM maintenance_schedule WHERE schedule_id = ? AND assigned_to_user_id = ?";
    $sch_stmt = $conn->prepare($sch_sql);
    $sch_stmt->bind_param("ii", $schedule_id, $user_id);
    $sch_stmt->execute();
    $sch_result = $sch_stmt->get_result();
    
    if ($row = $sch_result->fetch_assoc()) {
        // Fetch Equipment Name
        $eq_name_sql = "SELECT equipment_name FROM equipment WHERE id = ?";
        $eq_stmt = $conn->prepare($eq_name_sql);
        $eq_stmt->bind_param("i", $row['equipment_id']);
        $eq_stmt->execute();
        $eq_result = $eq_stmt->get_result();
        $equipment_name = ($eq_row = $eq_result->fetch_assoc()) ? $eq_row['equipment_name'] : "Equipment #".$row['equipment_id'];
        
        // Fetch last maintenance date
        $last_maint_sql = "SELECT MAX(completed_date) as last_date FROM maintenance WHERE maintenance_schedule_id = ?";
        $lm_stmt = $conn->prepare($last_maint_sql);
        $lm_stmt->bind_param("i", $schedule_id);
        $lm_stmt->execute();
        $lm_res = $lm_stmt->get_result();
        $last_maintenance_date = ($lm_row = $lm_res->fetch_assoc()) ? $lm_row['last_date'] : null;
        $lm_stmt->close();
        
        $maint_info = getNextMaintenanceDate($row['start_date'], $row['interval_value'], $row['interval_unit'], $last_maintenance_date);
        
        if ($maint_info['is_due']) {
            // It is due. Check if we already alerted since it became due.
            $due_date_ts = $maint_info['next_date']->getTimestamp();
            
            $check_notif = "SELECT notification_id FROM notification 
                            WHERE user_id = ? 
                            AND type = 'maintenance_due' 
                            AND message LIKE ? 
                            AND created_at >= FROM_UNIXTIME(?)";
            
            $msg_pattern = "%" . $equipment_name . "%";
            $stmt_check = $conn->prepare($check_notif);
            $stmt_check->bind_param("isi", $user_id, $msg_pattern, $due_date_ts);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows == 0) {
                // SEND ALERT
                
                // 1. DB Notification
                $notif_msg = "Maintenance Due: " . $equipment_name . " is ready for maintenance.";
                $notif_type = "maintenance_due";
                $ins_notif = "INSERT INTO notification (user_id, message, type, is_read) VALUES (?, ?, ?, 0)";
                $stmt_ins = $conn->prepare($ins_notif);
                $stmt_ins->bind_param("iss", $user_id, $notif_msg, $notif_type);
                $stmt_ins->execute();
                
                // 2. Email
                $email_status = 'skipped';
                $email_error = '';
                if (!empty($technician_email)) {
                    $subject = "Maintenance Alert: " . $equipment_name;
                    $htmlBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; background-color: #ffffff;'>
                        <div style='text-align: center; margin-bottom: 20px;'>
                            <img src='cid:company_logo' alt='Company Logo' style='max-width: 150px; height: auto;'>
                        </div>
                        <div style='background-color: #fef2f2; padding: 15px; border-left: 4px solid #ef4444; margin-bottom: 25px; border-radius: 0 4px 4px 0;'>
                            <h2 style='margin: 0; color: #991b1b; font-size: 20px;'>Maintenance Due Alert</h2>
                        </div>
                        <p style='color: #334155; font-size: 16px; line-height: 1.5;'>Dear <strong>" . htmlspecialchars($technician_name) . "</strong>,</p>
                        <p style='color: #334155; font-size: 16px; line-height: 1.5;'>The maintenance cycle for <strong>" . htmlspecialchars($equipment_name) . "</strong> has ended.</p>
                        
                        <div style='background-color: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748b; font-size: 14px; width: 120px;'>Equipment:</td>
                                    <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>" . htmlspecialchars($equipment_name) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748b; font-size: 14px;'>Alert Time:</td>
                                    <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>" . date('Y-m-d H:i:s') . "</td>
                                </tr>
                            </table>
                        </div>

                        <p style='color: #334155; font-size: 16px; line-height: 1.5;'>Please perform the required maintenance and log it in the system.</p>

                        <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; text-align: center;'>
                            <p>This is an automated notification from the Equipment Management System.</p>
                            <p>&copy; " . date('Y') . " Equipment Management System. All rights reserved.</p>
                        </div>
                    </div>";
                    
                    $mail_res = send_app_mail($technician_email, $technician_name, $subject, $htmlBody);
                    if ($mail_res['success']) {
                        $email_status = 'sent';
                    } else {
                        $email_status = 'failed';
                        $email_error = $mail_res['error'];
                    }
                }
                
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Alert sent', 
                    'email_status' => $email_status, 
                    'email_error' => $email_error
                ]);
            } else {
                echo json_encode(['status' => 'ignored', 'message' => 'Already alerted']);
            }
        } else {
             echo json_encode(['status' => 'ignored', 'message' => 'Not due yet']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Schedule not found']);
    }
    exit;
}

// Configuration
$admin_email = 'nendayishimiye@gmail.com';
$admin_name = 'Admin';

// Handle Maintenance Submission
if (isset($_POST['submit_maintenance'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $schedule_id = intval($_POST['schedule_id']);
    $description = trim($_POST['description']);
    $cost = floatval($_POST['cost']);
    $status = 'Completed'; // Default status for technician submission
    
    // Set Timezone
    date_default_timezone_set('Africa/Kigali'); 
    $completed_date = date('Y-m-d H:i:s');
    
    $maintenance_type = $_POST['maintenance_type'];

    $insert_sql = "INSERT INTO maintenance (equipment_id, user_id, maintenance_type, maintenance_schedule_id, completed_date, description, cost, statuss) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    if ($stmt) {
        $stmt->bind_param("iisissds", $equipment_id, $user_id, $maintenance_type, $schedule_id, $completed_date, $description, $cost, $status);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Maintenance recorded successfully!";

            // Fetch Equipment Name for Notification
            $eq_name_sql = "SELECT equipment_name FROM equipment WHERE id = ?";
            $eq_name_stmt = $conn->prepare($eq_name_sql);
            $equipment_name = "Unknown Equipment";
            if ($eq_name_stmt) {
                $eq_name_stmt->bind_param("i", $equipment_id);
                $eq_name_stmt->execute();
                $eq_name_result = $eq_name_stmt->get_result();
                if ($eq_row = $eq_name_result->fetch_assoc()) {
                    $equipment_name = $eq_row['equipment_name'];
                }
                $eq_name_stmt->close();
            }

            // Send Notification to Admin (User ID 1)
            $admin_id = 1;
            $notif_msg = "New maintenance recorded for " . $equipment_name;
            $notif_type = "maintenance";
            $notif_sql = "INSERT INTO notification (user_id, message, type, is_read) VALUES (?, ?, ?, 0)";
            $notif_stmt = $conn->prepare($notif_sql);
            if ($notif_stmt) {
                $notif_stmt->bind_param("iss", $admin_id, $notif_msg, $notif_type);
                $notif_stmt->execute();
                $notif_stmt->close();
            }

            // Send Email to Admin
            $subject = 'New Maintenance Recorded';
            


            $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <img src='cid:company_logo' alt='Company Logo' style='max-width: 150px; height: auto;'>
                </div>
                <div style='background-color: #f0f9ff; padding: 15px; border-left: 4px solid #0284c7; margin-bottom: 25px; border-radius: 0 4px 4px 0;'>
                    <h2 style='margin: 0; color: #0c4a6e; font-size: 20px;'>New Maintenance Recorded</h2>
                </div>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.5;'>
                    A new maintenance activity has been completed and recorded by the technician.
                </p>
                
                <div style='background-color: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 120px;'>Technician:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars($technician_name) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Equipment:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars($equipment_name) . " (ID: " . htmlspecialchars($equipment_id) . ")</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Description:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . nl2br(htmlspecialchars($description)) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Cost:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars(number_format($cost, 2)) . " RWF</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Date:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars($completed_date) . "</td>
                        </tr>
                    </table>
                </div>

                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 12px;'>
                    <p>&copy; " . date('Y') . " Equipment Management System. All rights reserved.</p>
                </div>
            </div>";
            
            $mailResult = send_app_mail($admin_email, $admin_name, $subject, $htmlBody);
            if (!$mailResult['success']) {
                // If email fails, append to success message or set a warning
                $_SESSION['success_message'] .= " (Warning: Email notification failed: " . $mailResult['error'] . ")";
            }
        } else {
            $_SESSION['error_message'] = "Error recording maintenance: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Database error: " . $conn->error;
    }
    
    header("Location: schedules_technician.php");
    exit();
}

// Handle Breakdown Submission
if (isset($_POST['submit_breakdown'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $status = 'Open'; 
    $breakdown_date = date('Y-m-d H:i:s');

    $insert_sql = "INSERT INTO breakdown (equipment_id, reported_by_user_id, breakdown_date, issue_description, priority, statuss) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    if ($stmt) {
        $stmt->bind_param("iissss", $equipment_id, $user_id, $breakdown_date, $description, $priority, $status);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Breakdown reported successfully!";

            // Fetch Equipment Name for Notification
            $eq_name_sql = "SELECT equipment_name FROM equipment WHERE id = ?";
            $eq_name_stmt = $conn->prepare($eq_name_sql);
            $equipment_name = "Unknown Equipment";
            if ($eq_name_stmt) {
                $eq_name_stmt->bind_param("i", $equipment_id);
                $eq_name_stmt->execute();
                $eq_name_result = $eq_name_stmt->get_result();
                if ($eq_row = $eq_name_result->fetch_assoc()) {
                    $equipment_name = $eq_row['equipment_name'];
                }
                $eq_name_stmt->close();
            }

            // Send Notification to Admin (User ID 1)
            $admin_id = 1;
            $notif_msg = "New breakdown reported for " . $equipment_name;
            $notif_type = "breakdown";
            $notif_sql = "INSERT INTO notification (user_id, message, type, is_read) VALUES (?, ?, ?, 0)";
            $notif_stmt = $conn->prepare($notif_sql);
            if ($notif_stmt) {
                $notif_stmt->bind_param("iss", $admin_id, $notif_msg, $notif_type);
                $notif_stmt->execute();
                $notif_stmt->close();
            }

            // Send Email to Admin
            $subject = 'New Breakdown Reported';
            


            $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <img src='cid:company_logo' alt='Company Logo' style='max-width: 150px; height: auto;'>
                </div>
                <div style='background-color: #fef2f2; padding: 15px; border-left: 4px solid #dc2626; margin-bottom: 25px; border-radius: 0 4px 4px 0;'>
                    <h2 style='margin: 0; color: #991b1b; font-size: 20px;'>New Breakdown Reported</h2>
                </div>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.5;'>
                    A new equipment breakdown has been reported by a technician.
                </p>
                
                <div style='background-color: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 120px;'>Technician:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars($technician_name) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Equipment:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars($equipment_name) . " (ID: " . htmlspecialchars($equipment_id) . ")</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Issue:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . nl2br(htmlspecialchars($description)) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Priority:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars($priority) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Date:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: 500;'>" . htmlspecialchars($breakdown_date) . "</td>
                        </tr>
                    </table>
                </div>

                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 12px;'>
                    <p>&copy; " . date('Y') . " Equipment Management System. All rights reserved.</p>
                </div>
            </div>";
            
            $mailResult = send_app_mail($admin_email, $admin_name, $subject, $htmlBody);
            if (!$mailResult['success']) {
                $_SESSION['success_message'] .= " (Warning: Email notification failed: " . $mailResult['error'] . ")";
            }
        } else {
            $_SESSION['error_message'] = "Error reporting breakdown: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Database error: " . $conn->error;
    }
    
    header("Location: schedules_technician.php");
    exit();
}

// Fetch schedules assigned to the logged-in technician
$schedules = [];
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
            e.equipment_image,
            (SELECT MAX(completed_date) FROM maintenance WHERE maintenance_schedule_id = ms.schedule_id) as last_maintenance_date
        FROM maintenance_schedule ms
        LEFT JOIN equipment e ON ms.equipment_id = e.id
        WHERE ms.assigned_to_user_id = ?
        ORDER BY ms.start_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
$stmt->close();

// Function to calculate next maintenance date based on created_at
// Function getNextMaintenanceDate moved to top of file
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Schedules - Technician</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/sidebar.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
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

        .created-date {
            font-size: 13px;
            color: var(--gray-500);
        }

        .btn-action {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-action.disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .btn-action.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .btn-action.hidden {
            display: none;
        }

        .equipment-image-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .equipment-image {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            background: #f3f4f6;
        }

        .equipment-image:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            transform: scale(1.05);
        }

        .equipment-info h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .equipment-info p {
            margin: 0;
            font-size: 12px;
            color: var(--gray-500);
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

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.due {
            background: #fecaca;
            color: #991b1b;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        @media (max-width: 768px) {
            .schedules-table {
                overflow-x: auto;
            }

            .schedules-table table {
                min-width: 600px;
            }
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
            <!-- Stats Mini -->
            <div class="stats-mini">
                <div class="stat-mini-card">
                    <div class="stat-mini-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h3><?php echo count($schedules); ?></h3>
                        <p>Assigned Schedules</p>
                    </div>
                </div>
            </div>

            <!-- Schedules Table -->
            <div class="schedules-table">
                <?php if (count($schedules) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Equipment</th>
                                <th>Maintenance Type</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($schedules as $schedule): 
                                $maintenance_info = getNextMaintenanceDate(
                                    $schedule['start_date'],
                                    $schedule['interval_value'],
                                    $schedule['interval_unit'],
                                    $schedule['last_maintenance_date'] ?? null
                                );
                                $is_due = $maintenance_info['is_due'];
                                $next_date = $maintenance_info['next_date'];
                                $seconds_until = $maintenance_info['seconds_until_due'];
                            ?>
                                <tr>
                                    <td class="schedule-id">#<?php echo $counter; ?></td>
                                    <td>
                                        <div class="equipment-image-cell">
                                            <?php if (!empty($schedule['equipment_image'])): ?>
                                                <img src="../uploads/equipment/<?php echo htmlspecialchars($schedule['equipment_image']); ?>" alt="<?php echo htmlspecialchars($schedule['equipment_name']); ?>" class="equipment-image">
                                            <?php else: ?>
                                                <div class="equipment-image" style="display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #d1d5db;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="equipment-info">
                                                <h4><?php echo htmlspecialchars($schedule['equipment_name'] ?? 'N/A'); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($schedule['maintenance_type']); ?></td>
                                    <td>
                                        <span class="created-date">
                                            <?php echo date('M d, Y', strtotime($schedule['start_date'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $is_due ? 'due' : 'pending'; ?>" data-schedule-id="<?php echo $schedule['schedule_id']; ?>" data-seconds="<?php echo $seconds_until; ?>">
                                            <i class="fas <?php echo $is_due ? 'fa-exclamation-circle' : 'fa-clock'; ?>"></i>
                                            <?php if ($is_due): ?>
                                                <span>Due Now</span>
                                            <?php else: ?>
                                                <span class="countdown-text">Due in <span class="countdown-value">--:--:--</span></span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="btn-action <?php echo !$is_due ? 'hidden' : ''; ?>" 
                                                data-schedule-id="<?php echo $schedule['schedule_id']; ?>" 
                                                data-equipment-id="<?php echo $schedule['equipment_id']; ?>"
                                                data-maintenance-type="<?php echo htmlspecialchars($schedule['maintenance_type']); ?>"
                                                data-equipment-name="<?php echo htmlspecialchars($schedule['equipment_name']); ?>"
                                                title="Fill this maintenance schedule">
                                                <i class="fas fa-check"></i> Fill
                                            </button>

                                            <button type="button" class="btn-action btn-breakdown" 
                                                style="background: #ef4444;"
                                                data-equipment-id="<?php echo $schedule['equipment_id']; ?>"
                                                data-equipment-name="<?php echo htmlspecialchars($schedule['equipment_name']); ?>"
                                                title="Report a Breakdown">
                                                <i class="fas fa-exclamation-triangle"></i> Report
                                            </button>
                                        </div>
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
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Schedules Assigned</h3>
                        <p>You have no maintenance schedules assigned to you at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Maintenance Fill Modal -->
    <div id="fillMaintenanceModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-content" style="background: white; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px; position: relative;">
            <span class="close-modal" style="position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</span>
            <h3 style="margin-top: 0; color: #1f2937; margin-bottom: 20px;">Fill Maintenance Report</h3>
            
            <form method="POST" action="" id="maintenanceForm">
                <input type="hidden" name="schedule_id" id="modal_schedule_id">
                <input type="hidden" name="equipment_id" id="modal_equipment_id">
                <input type="hidden" name="maintenance_type" id="modal_maintenance_type">
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500;">Equipment</label>
                    <input type="text" id="modal_equipment_name" readonly style="width: 100%; padding: 10px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500;">Description of Work</label>
                    <textarea name="description" required rows="4" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical;" placeholder="Describe the maintenance performed..."></textarea>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500;">Cost (RWF)</label>
                    <input type="number" name="cost" step="0.01" min="0" value="0.00" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn-cancel" style="padding: 10px 20px; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <input type="hidden" name="submit_maintenance" value="1">
                    <button type="submit" name="submit_maintenance" style="padding: 10px 20px; border: none; background: #2563eb; color: white; border-radius: 6px; cursor: pointer; font-weight: 600;">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Breakdown Report Modal -->
    <div id="breakdownModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; justify-content: center; align-items: center;">
        <div class="modal-content" style="background: white; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px; position: relative;">
            <span class="close-breakdown-modal" style="position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</span>
            <h3 style="margin-top: 0; color: #b91c1c; margin-bottom: 20px;">Report Breakdown</h3>
            
            <form method="POST" action="" id="breakdownForm">
                <input type="hidden" name="equipment_id" id="breakdown_equipment_id">
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500;">Equipment</label>
                    <input type="text" id="breakdown_equipment_name" readonly style="width: 100%; padding: 10px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500;">Issue Description</label>
                    <textarea name="description" required rows="4" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical;" placeholder="Describe the issue..."></textarea>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500;">Priority</label>
                    <select name="priority" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn-cancel-breakdown" style="padding: 10px 20px; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" name="submit_breakdown" style="padding: 10px 20px; border: none; background: #dc2626; color: white; border-radius: 6px; cursor: pointer; font-weight: 600;">Report Issue</button>
                </div>
            </form>
        </div>
    </div>

    <script src="./assets/js/script.js"></script>
    <script>
        // Modal Logic
        const modal = document.getElementById('fillMaintenanceModal');
        const breakdownModal = document.getElementById('breakdownModal');
        
        const closeBtn = document.querySelector('.close-modal');
        const closeBreakdownBtn = document.querySelector('.close-breakdown-modal');
        
        const cancelBtn = document.querySelector('.btn-cancel');
        const cancelBreakdownBtn = document.querySelector('.btn-cancel-breakdown');

        function closeModal() {
            modal.style.display = 'none';
        }

        function closeBreakdownModal() {
            breakdownModal.style.display = 'none';
        }

        closeBtn.onclick = closeModal;
        cancelBtn.onclick = closeModal;
        
        closeBreakdownBtn.onclick = closeBreakdownModal;
        cancelBreakdownBtn.onclick = closeBreakdownModal;

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == breakdownModal) {
                closeBreakdownModal();
            }
        }


        // Store schedules data for real-time updates
        const schedules = [
            <?php foreach ($schedules as $schedule): 
                $maintenance_info = getNextMaintenanceDate(
                    $schedule['start_date'],
                    $schedule['interval_value'],
                    $schedule['interval_unit'],
                    $schedule['last_maintenance_date'] ?? null
                );
            ?>
            {
                id: <?php echo $schedule['schedule_id']; ?>,
                start_date: '<?php echo htmlspecialchars($schedule['start_date']); ?>',
                interval_value: <?php echo $schedule['interval_value']; ?>,
                interval_unit: '<?php echo htmlspecialchars($schedule['interval_unit']); ?>',
                seconds_remaining: <?php echo $maintenance_info['seconds_until_due']; ?>,
                is_due: <?php echo $maintenance_info['is_due'] ? 'true' : 'false'; ?>
            },
            <?php endforeach; ?>
        ];

        // Track sent alerts and in-flight requests to avoid duplicates while allowing retries.
        const alertedSchedules = new Set();
        const alertRequestsInFlight = new Set();

        // Function to convert interval to seconds
        function getIntervalSeconds(interval_value, interval_unit) {
            const intervals = {
                'SECOND': 1,
                'MINUTE': 60,
                'HOUR': 3600,
                'DAY': 86400,
                'MONTH': 2592000,  // 30 days
                'YEAR': 31536000   // 365 days
            };
            return interval_value * (intervals[interval_unit] || 86400);
        }

        // Function to format time remaining
        function formatTimeRemaining(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // Update countdowns in real-time
        function updateScheduleStatus() {
            schedules.forEach(schedule => {
                const badge = document.querySelector(`.status-badge[data-schedule-id="${schedule.id}"]`);
                const button = document.querySelector(`.btn-action[data-schedule-id="${schedule.id}"]`);
                
                if (!badge || !button) return;
                
                // Skip if button is disabled (already completed)
                if (button.classList.contains('disabled')) return;

                // Decrement seconds if not already due
                if (schedule.seconds_remaining > 0 && !schedule.is_due) {
                    schedule.seconds_remaining--;
                }
                
                // Check if due (either initially due or countdown reached 0)
                const isDue = schedule.is_due || schedule.seconds_remaining <= 0;
                
                if (isDue) {
                    // Schedule is due
                    schedule.is_due = true;
                    schedule.seconds_remaining = 0;
                    badge.classList.add('due');
                    badge.classList.remove('pending');
                    badge.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Due Now</span>';
                    button.classList.remove('hidden');

                    // Trigger Alert if not already sent
                    if (!alertedSchedules.has(schedule.id) && !alertRequestsInFlight.has(schedule.id)) {
                        alertRequestsInFlight.add(schedule.id);
                        
                        // Send AJAX request to trigger email and notification
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `trigger_due_alert=1&schedule_id=${schedule.id}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                alertedSchedules.add(schedule.id);
                                console.log('Maintenance alert sent:', data.message);
                            } else {
                                console.log('Maintenance alert status:', data.message);
                                if (data.status === 'ignored' && data.message === 'Already alerted') {
                                    alertedSchedules.add(schedule.id);
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Error sending maintenance alert:', err);
                        })
                        .finally(() => {
                            alertRequestsInFlight.delete(schedule.id);
                        });
                    }
                } else {
                    // Schedule is pending
                    badge.classList.add('pending');
                    badge.classList.remove('due');
                    const timeFormatted = formatTimeRemaining(schedule.seconds_remaining);
                    badge.innerHTML = `<i class="fas fa-clock"></i><span>Due in <span class="countdown-value">${timeFormatted}</span></span>`;
                    button.classList.add('hidden');
                }
            });
        }

        // Update every second
        setInterval(updateScheduleStatus, 1000);

        // Initial update
        updateScheduleStatus();

        // Handle Fill button click
        document.querySelectorAll('.btn-action').forEach(button => {
            button.addEventListener('click', function() {
                if (this.classList.contains('disabled')) return;

                const scheduleId = this.getAttribute('data-schedule-id');
                const equipmentId = this.getAttribute('data-equipment-id');
                const equipmentName = this.getAttribute('data-equipment-name');
                const maintenanceType = this.getAttribute('data-maintenance-type');
                
                // Populate Modal
                document.getElementById('modal_schedule_id').value = scheduleId;
                document.getElementById('modal_equipment_id').value = equipmentId;
                document.getElementById('modal_equipment_name').value = equipmentName;
                document.getElementById('modal_maintenance_type').value = maintenanceType;
                
                // Show Modal
                modal.style.display = 'flex';
            });
        });

        // Handle Breakdown button click
        document.querySelectorAll('.btn-breakdown').forEach(button => {
            button.addEventListener('click', function() {
                const equipmentId = this.getAttribute('data-equipment-id');
                const equipmentName = this.getAttribute('data-equipment-name');
                
                // Populate Modal
                document.getElementById('breakdown_equipment_id').value = equipmentId;
                document.getElementById('breakdown_equipment_name').value = equipmentName;
                
                // Show Modal
                breakdownModal.style.display = 'flex';
            });
        });

        // Loading State for Maintenance Form
        const maintenanceForm = document.getElementById('maintenanceForm');
        if (maintenanceForm) {
            maintenanceForm.addEventListener('submit', function(e) {
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
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            });
        }

        // Loading State for Breakdown Form
        const breakdownForm = document.getElementById('breakdownForm');
        if (breakdownForm) {
            breakdownForm.addEventListener('submit', function(e) {
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
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reporting...';
            });
        }
    </script>
</body>
</html>
