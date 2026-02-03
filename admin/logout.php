<?php
session_start();

// Destroy all session data
session_destroy();

// Redirect to login page
header("Location: ../index.html?success=" . urlencode("You have been logged out successfully"));
exit();
?>
