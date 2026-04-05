<?php

// Common Header File

// Ensure config is loaded
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Attendify</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Font family -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                Attendify
            </div>
            
            <?php if (isLoggedIn()): ?>
            <!-- Hidden Checkbox for Menu Toggle -->
            <input type="checkbox" id="menu-toggle" class="menu-toggle">
            
            <!-- Hamburger Menu Label -->
            <label for="menu-toggle" class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </label>

            <nav>
                <ul>
                    <?php if ($_SESSION['user_type'] == 'admin'): ?>
                        <li><a href="../admin/dashboard.php">Dashboard</a></li>
                        <li><a href="../admin/list_users.php">Users</a></li>
                        <li><a href="../admin/list_students.php">Students</a></li>
                        <li><a href="../admin/list_teachers.php">Teachers</a></li>
                        <li><a href="../admin/list_sections.php">Sections</a></li>
                        <li><a href="../admin/list_subjects.php">Subjects</a></li>
                        <li><a href="../admin/reports.php">Reports</a></li>
                        
                    <?php elseif ($_SESSION['user_type'] == 'teacher'): ?>
                        <li><a href="../teacher/dashboard.php">Dashboard</a></li>
                        <li><a href="../teacher/my_classes.php">My Classes</a></li>
                        <li><a href="../teacher/list_session.php">Sessions</a></li>
                        <li><a href="../teacher/view_attendance.php">Attendance</a></li>
                        
                        
                    <?php elseif ($_SESSION['user_type'] == 'student'): ?>
                        <li><a href="../student/dashboard.php">Dashboard</a></li>
                        <li><a href="../student/mark_attendance.php">Mark Attendance</a></li>
                        <li><a href="../student/my_attendance.php">My Attendance</a></li>
                        <li><a href="../student/my_schedule.php">Schedule</a></li>
                        <li><a href="../student/notifications.php">Notifications</a></li>
                    <?php endif; ?>
                    
                    <li><a href="../logout.php" class="text-danger">Logout</a></li>
                </ul>
            </nav>
            
            <div class="user-info">
                <span class="badge badge-primary"><?php echo ucfirst($_SESSION['user_type']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <main>
        <div class="container">