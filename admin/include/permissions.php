<?php
// Centralized role rules for admin pages.
// Roles: 1 = Admin, 2 = Technician, 3 = Super Admin (view-only)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROLE_ADMIN', 1);
define('ROLE_TECHNICIAN', 2);
define('ROLE_SUPER_ADMIN', 3);

function current_role_id() {
    return (int)($_SESSION['role_id'] ?? 0);
}

function is_view_all_role($role_id) {
    return ($role_id === ROLE_ADMIN || $role_id === ROLE_SUPER_ADMIN);
}

function can_manage_admin_data($role_id) {
    // Only Admin (role 1) can create/update/delete in admin pages.
    return ($role_id === ROLE_ADMIN);
}

function is_super_admin_readonly($role_id) {
    return ($role_id === ROLE_SUPER_ADMIN);
}

function redirect_with_error($target, $message) {
    $glue = (strpos($target, '?') === false) ? '?' : '&';
    header("Location: " . $target . $glue . "error=" . urlencode($message));
    exit();
}

