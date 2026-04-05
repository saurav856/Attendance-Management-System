<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', '25123840');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");


// Sanitize input data to prevent SQL injection and XSS
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// Redirect to appropriate dashboard based on user role
function redirectToDashboard() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    switch ($_SESSION['user_type']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'teacher':
            header("Location: teacher/dashboard.php");
            break;
        case 'student':
            header("Location: student/dashboard.php");
            break;
        default:
            header("Location: login.php");
            break;
    }
    exit();
}

// Check authentication and required role
function checkAuth($required_role = null) {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
    
    if ($required_role && $_SESSION['user_type'] != $required_role) {
        header("Location: ../login.php");
        exit();
    }
}

// Format date for display (e.g., Jan 10, 2025)
function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// Format datetime for display (e.g., Jan 10, 2025 02:30 PM)
function formatDateTime($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

// Format time for display (e.g., 09:00 AM)
function formatTime($time) {
    if (empty($time)) return 'N/A';
    return date('h:i A', strtotime($time));
}

// Get attendance status badge HTML
function getStatusBadge($status) {
    $badges = [
        'present' => '<span class="badge badge-success">Present</span>',
        'absent' => '<span class="badge badge-danger">Absent</span>',
        'late' => '<span class="badge badge-warning">Late</span>',
        'active' => '<span class="badge badge-success">Active</span>',
        'inactive' => '<span class="badge badge-secondary">Inactive</span>',
        'graduated' => '<span class="badge badge-info">Graduated</span>',
        'dropped' => '<span class="badge badge-danger">Dropped</span>',
        'completed' => '<span class="badge badge-success">Completed</span>'
    ];
    
    return isset($badges[$status]) ? $badges[$status] : '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

// Get user type badge HTML
function getUserTypeBadge($type) {
    $badges = [
        'admin' => '<span class="badge badge-danger">Admin</span>',
        'teacher' => '<span class="badge badge-primary">Teacher</span>',
        'student' => '<span class="badge badge-success">Student</span>'
    ];
    
    return isset($badges[$type]) ? $badges[$type] : '<span class="badge badge-secondary">' . ucfirst($type) . '</span>';
}

// Calculate attendance percentage
function calculateAttendancePercentage($present, $total) {
    if ($total == 0) return 0;
    return round(($present / $total) * 100, 2);
}

// Get attendance percentage badge with color coding
function getAttendancePercentageBadge($percentage) {
    if ($percentage >= 75) {
        return '<span class="badge badge-success">' . $percentage . '%</span>';
    } elseif ($percentage >= 60) {
        return '<span class="badge badge-warning">' . $percentage . '%</span>';
    } else {
        return '<span class="badge badge-danger">' . $percentage . '%</span>';
    }
}

// Display success message
function showSuccess($message) {
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> ' . htmlspecialchars($message) . '
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
}


// Display error message
function showError($message) {
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($message) . '
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
}

// Display warning message
function showWarning($message) {
    return '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($message) . '
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
}

// Display info message
function showInfo($message) {
    return '<div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i> ' . htmlspecialchars($message) . '
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
}

// Get current academic year
function getCurrentAcademicYear() {
    $current_year = date('Y');
    $current_month = date('n');
    
    // If month is August or later, academic year is current_year to next_year
    if ($current_month >= 8) {
        return $current_year . '-' . ($current_year + 1);
    } else {
        // If month is before August, academic year is previous_year to current_year
        return ($current_year - 1) . '-' . $current_year;
    }
}

// Check if session is active (within time range)
function isSessionActive($session_date, $start_time, $end_time) {
    $current_datetime = date('Y-m-d H:i:s');
    $session_start = $session_date . ' ' . $start_time;
    $session_end = $session_date . ' ' . $end_time;
    
    return ($current_datetime >= $session_start && $current_datetime <= $session_end);
}

// Generate random password
function generatePassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (basic Nepal format)
function isValidPhone($phone) {
    // Nepal phone format: 10 digits starting with 98
    return preg_match('/^98\d{8}$/', $phone);
}

// Escape output for HTML display
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Redirect to a page
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check if request method is POST
function isPostRequest() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Get value from POST or return default
function post($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// Get value from GET or return default
function get($key, $default = '') {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

?>