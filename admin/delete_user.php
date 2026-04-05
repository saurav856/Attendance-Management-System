<?php
/**
 * Admin - Delete User (DELETE)
 * Confirm and delete user account
 */

// Include configuration and check authentication
require_once '../config.php';
checkAuth('admin');

// Set page title
$page_title = "Delete User";

$error = '';
$success = '';

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id == 0) {
    header("Location: list_users.php");
    exit();
}

// Prevent admin from deleting themselves
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error_message'] = "You cannot delete your own account!";
    header("Location: list_users.php");
    exit();
}

// Fetch user data for confirmation
$sql = "SELECT 
    u.user_id,
    u.username,
    u.user_type,
    CASE 
        WHEN u.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
        WHEN u.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
        WHEN u.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
    END as full_name,
    CASE 
        WHEN u.user_type = 'student' THEN s.email
        WHEN u.user_type = 'teacher' THEN t.email
        WHEN u.user_type = 'admin' THEN a.email
    END as email,
    s.student_card_id,
    u.created_at
FROM USERS u
LEFT JOIN STUDENTS s ON u.user_id = s.user_id AND u.user_type = 'student'
LEFT JOIN TEACHERS t ON u.user_id = t.user_id AND u.user_type = 'teacher'
LEFT JOIN ADMINS a ON u.user_id = a.user_id AND u.user_type = 'admin'
WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: list_users.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Handle deletion confirmation
if (isPostRequest() && post('confirm_delete') == 'yes') {
    
    // Additional check: Get related records count
    $related_data = [];
    
    if ($user['user_type'] == 'student') {
        // Check attendance records
        $attendance_sql = "SELECT COUNT(*) as count FROM ATTENDANCE WHERE student_id = (SELECT student_id FROM STUDENTS WHERE user_id = ?)";
        $attendance_stmt = $conn->prepare($attendance_sql);
        $attendance_stmt->bind_param("i", $user_id);
        $attendance_stmt->execute();
        $attendance_result = $attendance_stmt->get_result();
        $attendance_count = $attendance_result->fetch_assoc()['count'];
        $related_data['attendance_records'] = $attendance_count;
        $attendance_stmt->close();
        
        // Check enrollments
        $enrollment_sql = "SELECT COUNT(*) as count FROM ENROLLMENTS WHERE student_id = (SELECT student_id FROM STUDENTS WHERE user_id = ?)";
        $enrollment_stmt = $conn->prepare($enrollment_sql);
        $enrollment_stmt->bind_param("i", $user_id);
        $enrollment_stmt->execute();
        $enrollment_result = $enrollment_stmt->get_result();
        $enrollment_count = $enrollment_result->fetch_assoc()['count'];
        $related_data['enrollments'] = $enrollment_count;
        $enrollment_stmt->close();
        
    } elseif ($user['user_type'] == 'teacher') {
        // Check assigned subjects
        $subjects_sql = "SELECT COUNT(*) as count FROM SECTION_SUBJECTS WHERE teacher_id = (SELECT teacher_id FROM TEACHERS WHERE user_id = ?)";
        $subjects_stmt = $conn->prepare($subjects_sql);
        $subjects_stmt->bind_param("i", $user_id);
        $subjects_stmt->execute();
        $subjects_result = $subjects_stmt->get_result();
        $subjects_count = $subjects_result->fetch_assoc()['count'];
        $related_data['assigned_subjects'] = $subjects_count;
        $subjects_stmt->close();
        
        // Check attendance sessions
        $sessions_sql = "SELECT COUNT(*) as count FROM ATTENDANCE_SESSIONS WHERE teacher_id = (SELECT teacher_id FROM TEACHERS WHERE user_id = ?)";
        $sessions_stmt = $conn->prepare($sessions_sql);
        $sessions_stmt->bind_param("i", $user_id);
        $sessions_stmt->execute();
        $sessions_result = $sessions_stmt->get_result();
        $sessions_count = $sessions_result->fetch_assoc()['count'];
        $related_data['attendance_sessions'] = $sessions_count;
        $sessions_stmt->close();
    }
    
    // Delete user (CASCADE will handle related records)
    $delete_sql = "DELETE FROM USERS WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "User '" . htmlspecialchars($user['username']) . "' has been deleted successfully!";
        header("Location: list_users.php");
        exit();
    } else {
        $error = "Error deleting user: " . $conn->error;
    }
    
    $delete_stmt->close();
}

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-times"></i> Delete User</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_users.php">Users</a>
            <span>/</span>
            <span>Delete User</span>
        </div>
    </div>
    <div>
        <a href="list_users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): ?>
    <?php echo showError($error); ?>
<?php endif; ?>

<!-- Delete Confirmation Card -->
<div class="card">
    <div class="card-header" style="background-color: #fee2e2; border-bottom: 2px solid #ef4444;">
        <h2 class="card-title" style="color: #991b1b;">
            <i class="fas fa-exclamation-triangle"></i> Confirm User Deletion
        </h2>
    </div>
    <div class="card-body">
        
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Warning:</strong> This action cannot be undone! Deleting this user will permanently remove all associated data.
        </div>
        
        <h3 style="margin-bottom: 20px; color: #1f2937;">User Information</h3>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 200px 1fr; gap: 15px;">
                <div><strong>User ID:</strong></div>
                <div><?php echo $user['user_id']; ?></div>
                
                <div><strong>Username:</strong></div>
                <div><?php echo e($user['username']); ?></div>
                
                <div><strong>Full Name:</strong></div>
                <div><?php echo e($user['full_name']); ?></div>
                
                <div><strong>Email:</strong></div>
                <div><?php echo $user['email'] ? e($user['email']) : '<span class="text-muted">N/A</span>'; ?></div>
                
                <div><strong>User Type:</strong></div>
                <div><?php echo getUserTypeBadge($user['user_type']); ?></div>
                
                <?php if ($user['user_type'] == 'student' && $user['student_card_id']): ?>
                    <div><strong>Card ID:</strong></div>
                    <div><?php echo e($user['student_card_id']); ?></div>
                <?php endif; ?>
                
                <div><strong>Created At:</strong></div>
                <div><?php echo formatDateTime($user['created_at']); ?></div>
            </div>
        </div>
        
        <!-- Show related data warning -->
        <?php
        // Count related records for display
        if ($user['user_type'] == 'student') {
            $attendance_sql = "SELECT COUNT(*) as count FROM ATTENDANCE WHERE student_id = (SELECT student_id FROM STUDENTS WHERE user_id = ?)";
            $attendance_stmt = $conn->prepare($attendance_sql);
            $attendance_stmt->bind_param("i", $user_id);
            $attendance_stmt->execute();
            $attendance_result = $attendance_stmt->get_result();
            $attendance_count = $attendance_result->fetch_assoc()['count'];
            $attendance_stmt->close();
            
            $enrollment_sql = "SELECT COUNT(*) as count FROM ENROLLMENTS WHERE student_id = (SELECT student_id FROM STUDENTS WHERE user_id = ?)";
            $enrollment_stmt = $conn->prepare($enrollment_sql);
            $enrollment_stmt->bind_param("i", $user_id);
            $enrollment_stmt->execute();
            $enrollment_result = $enrollment_stmt->get_result();
            $enrollment_count = $enrollment_result->fetch_assoc()['count'];
            $enrollment_stmt->close();
            
            if ($attendance_count > 0 || $enrollment_count > 0) {
                echo '<div class="alert alert-warning">';
                echo '<i class="fas fa-info-circle"></i> ';
                echo '<strong>Related Data:</strong><br>';
                echo '<ul style="margin: 10px 0 0 20px;">';
                if ($attendance_count > 0) {
                    echo '<li>' . $attendance_count . ' attendance record(s) will be deleted</li>';
                }
                if ($enrollment_count > 0) {
                    echo '<li>' . $enrollment_count . ' enrollment(s) will be deleted</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
        } elseif ($user['user_type'] == 'teacher') {
            $subjects_sql = "SELECT COUNT(*) as count FROM SECTION_SUBJECTS WHERE teacher_id = (SELECT teacher_id FROM TEACHERS WHERE user_id = ?)";
            $subjects_stmt = $conn->prepare($subjects_sql);
            $subjects_stmt->bind_param("i", $user_id);
            $subjects_stmt->execute();
            $subjects_result = $subjects_stmt->get_result();
            $subjects_count = $subjects_result->fetch_assoc()['count'];
            $subjects_stmt->close();
            
            $sessions_sql = "SELECT COUNT(*) as count FROM ATTENDANCE_SESSIONS WHERE teacher_id = (SELECT teacher_id FROM TEACHERS WHERE user_id = ?)";
            $sessions_stmt = $conn->prepare($sessions_sql);
            $sessions_stmt->bind_param("i", $user_id);
            $sessions_stmt->execute();
            $sessions_result = $sessions_stmt->get_result();
            $sessions_count = $sessions_result->fetch_assoc()['count'];
            $sessions_stmt->close();
            
            if ($subjects_count > 0 || $sessions_count > 0) {
                echo '<div class="alert alert-warning">';
                echo '<i class="fas fa-info-circle"></i> ';
                echo '<strong>Related Data:</strong><br>';
                echo '<ul style="margin: 10px 0 0 20px;">';
                if ($subjects_count > 0) {
                    echo '<li>' . $subjects_count . ' subject assignment(s) will be deleted</li>';
                }
                if ($sessions_count > 0) {
                    echo '<li>' . $sessions_count . ' attendance session(s) will be deleted</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }
        ?>
        
        <form method="POST" action="" style="margin-top: 30px;">
            <input type="hidden" name="confirm_delete" value="yes">
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Are you absolutely sure you want to delete this user? This action CANNOT be undone!');">
                    <i class="fas fa-trash"></i> Yes, Delete User
                </button>
                <a href="list_users.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> No, Cancel
                </a>
            </div>
        </form>
        
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>