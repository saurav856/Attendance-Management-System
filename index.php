<?php

// Include configuration
require_once 'config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to appropriate dashboard based on user type
    redirectToDashboard();
} else {
    // Not logged in, redirect to login page
    header("Location: login.php");
    exit();
}
?>