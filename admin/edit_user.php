<?php
/**
 * Admin - Edit User (UPDATE)
 * Form to edit existing user account
 */

// Include configuration and check authentication
require_once '../config.php';
checkAuth('admin');

// Set page title
$page_title = "Edit User";

$error = '';
$success = '';

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id == 0) {
    header("Location: list_users.php");
    exit();
}

// Fetch existing user data
$sql = "SELECT 
    u.user_id,
    u.username,
    u.user_type,
    u.is_active,
    CASE 
        WHEN u.user_type = 'student' THEN s.student_id
        WHEN u.user_type = 'teacher' THEN t.teacher_id
        WHEN u.user_type = 'admin' THEN a.admin_id
    END as profile_id,
    CASE 
        WHEN u.user_type = 'student' THEN s.first_name
        WHEN u.user_type = 'teacher' THEN t.first_name
        WHEN u.user_type = 'admin' THEN a.first_name
    END as first_name,
    CASE 
        WHEN u.user_type = 'student' THEN s.last_name
        WHEN u.user_type = 'teacher' THEN t.last_name
        WHEN u.user_type = 'admin' THEN a.last_name
    END as last_name,
    CASE 
        WHEN u.user_type = 'student' THEN s.email
        WHEN u.user_type = 'teacher' THEN t.email
        WHEN u.user_type = 'admin' THEN a.email
    END as email,
    CASE 
        WHEN u.user_type = 'student' THEN s.phone
        WHEN u.user_type = 'teacher' THEN t.phone
        WHEN u.user_type = 'admin' THEN a.phone
    END as phone,
    s.student_card_id,
    s.enrollment_date,
    s.status as student_status,
    t.department,
    t.joining_date
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

// Handle form submission
if (isPostRequest()) {
    // Get form data
    $username = sanitize(post('username'));
    $user_type = $user['user_type']; // Cannot change user type
    $first_name = sanitize(post('first_name'));
    $last_name = sanitize(post('last_name'));
    $email = sanitize(post('email'));
    $phone = sanitize(post('phone'));
    $is_active = post('is_active') ? 1 : 0;
    
    // Password (optional - only update if provided)
    $password = post('password');
    $confirm_password = post('confirm_password');
    
    // Additional fields based on user type
    $student_card_id = sanitize(post('student_card_id'));
    $enrollment_date = sanitize(post('enrollment_date'));
    $student_status = sanitize(post('student_status'));
    $department = sanitize(post('department'));
    $joining_date = sanitize(post('joining_date'));
    
    // Validation
    if (empty($username) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!empty($email) && !isValidEmail($email)) {
        $error = "Invalid email format.";
    } else {
        // Check if username already exists (excluding current user)
        $check_sql = "SELECT user_id FROM USERS WHERE username = ? AND user_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username already exists. Please choose a different username.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update USERS table
                if (!empty($password)) {
                    // Update with new password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $user_sql = "UPDATE USERS SET username = ?, password_hash = ?, is_active = ? WHERE user_id = ?";
                    $user_stmt = $conn->prepare($user_sql);
                    $user_stmt->bind_param("ssii", $username, $password_hash, $is_active, $user_id);
                } else {
                    // Update without changing password
                    $user_sql = "UPDATE USERS SET username = ?, is_active = ? WHERE user_id = ?";
                    $user_stmt = $conn->prepare($user_sql);
                    $user_stmt->bind_param("sii", $username, $is_active, $user_id);
                }
                $user_stmt->execute();
                
                // Update type-specific table
                if ($user_type == 'student') {
                    // Validate student-specific fields
                    if (empty($student_card_id) || empty($enrollment_date)) {
                        throw new Exception("Student Card ID and Enrollment Date are required for students.");
                    }
                    
                    // Check if card ID already exists (excluding current student)
                    $card_check_sql = "SELECT student_id FROM STUDENTS WHERE student_card_id = ? AND user_id != ?";
                    $card_check_stmt = $conn->prepare($card_check_sql);
                    $card_check_stmt->bind_param("si", $student_card_id, $user_id);
                    $card_check_stmt->execute();
                    $card_check_result = $card_check_stmt->get_result();
                    
                    if ($card_check_result->num_rows > 0) {
                        throw new Exception("Student Card ID already exists.");
                    }
                    
                    $student_sql = "UPDATE STUDENTS SET 
                                   student_card_id = ?, 
                                   first_name = ?, 
                                   last_name = ?, 
                                   email = ?, 
                                   phone = ?, 
                                   enrollment_date = ?,
                                   status = ?
                                   WHERE user_id = ?";
                    $student_stmt = $conn->prepare($student_sql);
                    $student_stmt->bind_param("sssssssi", $student_card_id, $first_name, $last_name, $email, $phone, $enrollment_date, $student_status, $user_id);
                    $student_stmt->execute();
                    
                } elseif ($user_type == 'teacher') {
                    $teacher_sql = "UPDATE TEACHERS SET 
                                   first_name = ?, 
                                   last_name = ?, 
                                   email = ?, 
                                   phone = ?, 
                                   department = ?, 
                                   joining_date = ?
                                   WHERE user_id = ?";
                    $teacher_stmt = $conn->prepare($teacher_sql);
                    $teacher_stmt->bind_param("ssssssi", $first_name, $last_name, $email, $phone, $department, $joining_date, $user_id);
                    $teacher_stmt->execute();
                    
                } elseif ($user_type == 'admin') {
                    $admin_sql = "UPDATE ADMINS SET 
                                 first_name = ?, 
                                 last_name = ?, 
                                 email = ?, 
                                 phone = ?
                                 WHERE user_id = ?";
                    $admin_stmt = $conn->prepare($admin_sql);
                    $admin_stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);
                    $admin_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $success = "User updated successfully!";
                
                // Refresh user data
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error updating user: " . $e->getMessage();
            }
        }
        
        $check_stmt->close();
    }
}

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-edit"></i> Edit User</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_users.php">Users</a>
            <span>/</span>
            <span>Edit User</span>
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

<?php if ($success): ?>
    <?php echo showSuccess($success); ?>
<?php endif; ?>

<!-- Edit User Form -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-user-edit"></i> Edit User Information
            <?php echo getUserTypeBadge($user['user_type']); ?>
        </h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            
            <!-- Account Information Section -->
            <h3 style="margin-bottom: 20px; color: #2563eb; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                <i class="fas fa-key"></i> Account Information
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username" class="required">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter username" 
                        required
                        minlength="3"
                        value="<?php echo e($user['username']); ?>"
                    >
                    <small class="text-muted">Must be at least 3 characters long</small>
                </div>
                
                <div class="form-group">
                    <label>User Type</label>
                    <input 
                        type="text" 
                        value="<?php echo ucfirst($user['user_type']); ?>" 
                        disabled
                        style="background-color: #f3f4f6; cursor: not-allowed;"
                    >
                    <small class="text-muted">User type cannot be changed</small>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Leave password fields empty if you don't want to change the password.
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">New Password (Optional)</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter new password (leave empty to keep current)" 
                        minlength="6"
                    >
                    <small class="text-muted">Must be at least 6 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Confirm new password" 
                        minlength="6"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                    Account is Active
                </label>
            </div>
            
            <!-- Personal Information Section -->
            <h3 style="margin: 30px 0 20px 0; color: #2563eb; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                <i class="fas fa-user"></i> Personal Information
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name" class="required">First Name</label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        placeholder="Enter first name" 
                        required
                        value="<?php echo e($user['first_name']); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="last_name" class="required">Last Name</label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        placeholder="Enter last name" 
                        required
                        value="<?php echo e($user['last_name']); ?>"
                    >
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter email address"
                        value="<?php echo e($user['email']); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        placeholder="Enter phone number"
                        value="<?php echo e($user['phone']); ?>"
                    >
                </div>
            </div>
            
            <!-- Student-specific fields -->
            <?php if ($user['user_type'] == 'student'): ?>
                <h3 style="margin: 30px 0 20px 0; color: #10b981; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                    <i class="fas fa-id-card"></i> Student Information
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="student_card_id" class="required">Student Card ID</label>
                        <input 
                            type="text" 
                            id="student_card_id" 
                            name="student_card_id" 
                            placeholder="Enter student card ID"
                            required
                            value="<?php echo e($user['student_card_id']); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="enrollment_date" class="required">Enrollment Date</label>
                        <input 
                            type="date" 
                            id="enrollment_date" 
                            name="enrollment_date"
                            required
                            value="<?php echo e($user['enrollment_date']); ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="student_status" class="required">Status</label>
                    <select id="student_status" name="student_status" required>
                        <option value="active" <?php echo $user['student_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $user['student_status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="graduated" <?php echo $user['student_status'] == 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                    </select>
                </div>
            <?php endif; ?>
            
            <!-- Teacher-specific fields -->
            <?php if ($user['user_type'] == 'teacher'): ?>
                <h3 style="margin: 30px 0 20px 0; color: #f59e0b; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                    <i class="fas fa-chalkboard-teacher"></i> Teacher Information
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input 
                            type="text" 
                            id="department" 
                            name="department" 
                            placeholder="Enter department"
                            value="<?php echo e($user['department']); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="joining_date">Joining Date</label>
                        <input 
                            type="date" 
                            id="joining_date" 
                            name="joining_date"
                            value="<?php echo e($user['joining_date']); ?>"
                        >
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Form Actions -->
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">
                    Update User
                </button>
                <a href="list_users.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
            
        </form>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>