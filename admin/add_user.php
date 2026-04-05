<?php
/**
 * Admin - Add New User (CREATE)
 * Form to create a new user account
 */

// Include configuration and check authentication
require_once '../config.php';
checkAuth('admin');

// Set page title
$page_title = "Add New User";

$error = '';
$success = '';

// Handle form submission
if (isPostRequest()) {
    // Get form data
    $username = sanitize(post('username'));
    $password = post('password');
    $confirm_password = post('confirm_password');
    $user_type = sanitize(post('user_type'));
    $first_name = sanitize(post('first_name'));
    $last_name = sanitize(post('last_name'));
    $email = sanitize(post('email'));
    $phone = sanitize(post('phone'));
    $is_active = post('is_active') ? 1 : 0;
    
    // Additional fields based on user type
    $student_card_id = sanitize(post('student_card_id'));
    $enrollment_date = sanitize(post('enrollment_date'));
    $department = sanitize(post('department'));
    $joining_date = sanitize(post('joining_date'));
    
    // Validation
    if (empty($username) || empty($password) || empty($user_type) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!in_array($user_type, ['admin', 'teacher', 'student'])) {
        $error = "Invalid user type selected.";
    } elseif (!empty($email) && !isValidEmail($email)) {
        $error = "Invalid email format.";
    } else {
        // Check if username already exists
        $check_sql = "SELECT user_id FROM USERS WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username already exists. Please choose a different username.";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert into USERS table
                $user_sql = "INSERT INTO USERS (username, password_hash, user_type, is_active) VALUES (?, ?, ?, ?)";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("sssi", $username, $password_hash, $user_type, $is_active);
                $user_stmt->execute();
                
                // Get the inserted user_id
                $user_id = $conn->insert_id;
                
                // Insert into type-specific table
                if ($user_type == 'student') {
                    // Validate student-specific fields
                    if (empty($student_card_id) || empty($enrollment_date)) {
                        throw new Exception("Student Card ID and Enrollment Date are required for students.");
                    }
                    
                    // Check if card ID already exists
                    $card_check_sql = "SELECT student_id FROM STUDENTS WHERE student_card_id = ?";
                    $card_check_stmt = $conn->prepare($card_check_sql);
                    $card_check_stmt->bind_param("s", $student_card_id);
                    $card_check_stmt->execute();
                    $card_check_result = $card_check_stmt->get_result();
                    
                    if ($card_check_result->num_rows > 0) {
                        throw new Exception("Student Card ID already exists.");
                    }
                    
                    $student_sql = "INSERT INTO STUDENTS (user_id, student_card_id, first_name, last_name, email, phone, enrollment_date) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $student_stmt = $conn->prepare($student_sql);
                    $student_stmt->bind_param("issssss", $user_id, $student_card_id, $first_name, $last_name, $email, $phone, $enrollment_date);
                    $student_stmt->execute();
                    
                } elseif ($user_type == 'teacher') {
                    $teacher_sql = "INSERT INTO TEACHERS (user_id, first_name, last_name, email, phone, department, joining_date) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $teacher_stmt = $conn->prepare($teacher_sql);
                    $teacher_stmt->bind_param("issssss", $user_id, $first_name, $last_name, $email, $phone, $department, $joining_date);
                    $teacher_stmt->execute();
                    
                } elseif ($user_type == 'admin') {
                    $admin_sql = "INSERT INTO ADMINS (user_id, first_name, last_name, email, phone) 
                                 VALUES (?, ?, ?, ?, ?)";
                    $admin_stmt = $conn->prepare($admin_sql);
                    $admin_stmt->bind_param("issss", $user_id, $first_name, $last_name, $email, $phone);
                    $admin_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $success = "User created successfully!";
                
                // Redirect after 2 seconds
                header("refresh:2;url=list_users.php");
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error creating user: " . $e->getMessage();
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
        <h1><i class="fas fa-user-plus"></i> Add New User</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_users.php">Users</a>
            <span>/</span>
            <span>Add New</span>
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

<!-- Add User Form -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-user-plus"></i> User Information</h2>
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
                        value="<?php echo post('username'); ?>"
                    >
                    <small class="text-muted">Must be at least 3 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="user_type" class="required">User Type</label>
                    <select id="user_type" name="user_type" required onchange="toggleUserTypeFields()">
                        <option value="">-- Select User Type --</option>
                        <option value="admin" <?php echo post('user_type') == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="teacher" <?php echo post('user_type') == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="student" <?php echo post('user_type') == 'student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="required">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter password" 
                        required
                        minlength="6"
                    >
                    <small class="text-muted">Must be at least 6 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="required">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Confirm password" 
                        required
                        minlength="6"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" checked>
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
                        value="<?php echo post('first_name'); ?>"
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
                        value="<?php echo post('last_name'); ?>"
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
                        value="<?php echo post('email'); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        placeholder="Enter phone number"
                        value="<?php echo post('phone'); ?>"
                    >
                </div>
            </div>
            
            <!-- Student-specific fields -->
            <div id="student_fields" style="display: none;">
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
                            value="<?php echo post('student_card_id'); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="enrollment_date" class="required">Enrollment Date</label>
                        <input 
                            type="date" 
                            id="enrollment_date" 
                            name="enrollment_date"
                            value="<?php echo post('enrollment_date', date('Y-m-d')); ?>"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Teacher-specific fields -->
            <div id="teacher_fields" style="display: none;">
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
                            value="<?php echo post('department'); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="joining_date">Joining Date</label>
                        <input 
                            type="date" 
                            id="joining_date" 
                            name="joining_date"
                            value="<?php echo post('joining_date', date('Y-m-d')); ?>"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">
                    Create User
                </button>
                <a href="list_users.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
            
        </form>
    </div>
</div>

<script>
// Toggle user type specific fields
function toggleUserTypeFields() {
    var userType = document.getElementById('user_type').value;
    var studentFields = document.getElementById('student_fields');
    var teacherFields = document.getElementById('teacher_fields');
    
    // Hide all type-specific fields
    studentFields.style.display = 'none';
    teacherFields.style.display = 'none';
    
    // Remove required attribute from hidden fields
    document.getElementById('student_card_id').removeAttribute('required');
    document.getElementById('enrollment_date').removeAttribute('required');
    
    // Show relevant fields
    if (userType === 'student') {
        studentFields.style.display = 'block';
        document.getElementById('student_card_id').setAttribute('required', 'required');
        document.getElementById('enrollment_date').setAttribute('required', 'required');
    } else if (userType === 'teacher') {
        teacherFields.style.display = 'block';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleUserTypeFields();
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>