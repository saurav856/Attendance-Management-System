<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Enroll Student";

$error = '';
$success = '';

if (isPostRequest()) {
    $student_id = intval(post('student_id'));
    $section_id = intval(post('section_id'));
    $enrollment_date = sanitize(post('enrollment_date'));
    $status = sanitize(post('status'));
    
    if (empty($student_id) || empty($section_id) || empty($enrollment_date)) {
        $error = "Please fill in all required fields.";
    } else {
        $check_sql = "SELECT enrollment_id FROM ENROLLMENTS WHERE student_id = ? AND section_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $student_id, $section_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "This student is already enrolled in the selected section.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $conn->begin_transaction();
            
            try {
                $insert_sql = "INSERT INTO ENROLLMENTS (student_id, section_id, enrollment_date, status) 
                              VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiss", $student_id, $section_id, $enrollment_date, $status);
                $insert_stmt->execute();
                
                $update_sql = "UPDATE SECTIONS SET total_students = (
                                SELECT COUNT(*) FROM ENROLLMENTS WHERE section_id = ? AND status = 'active'
                              ) WHERE section_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $section_id, $section_id);
                $update_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['success_message'] = "Student enrolled successfully!";
                header("Location: list_enrollments.php");
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error enrolling student: " . $e->getMessage();
            }
        }
    }
}

$students_sql = "SELECT student_id, student_card_id, first_name, last_name FROM STUDENTS WHERE status = 'active' ORDER BY first_name, last_name";
$students_result = $conn->query($students_sql);

$sections_sql = "SELECT section_id, section_name, academic_year, semester FROM SECTIONS WHERE is_active = 1 ORDER BY section_name";
$sections_result = $conn->query($sections_sql);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-user-plus"></i> Enroll Student in Section</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_enrollments.php">Enrollments</a>
            <span>/</span>
            <span>Enroll Student</span>
        </div>
    </div>
    <div>
        <a href="list_enrollments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-user-check"></i> Enrollment Information</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            
            <div class="form-group">
                <label for="student_id" class="required">Select Student</label>
                <select id="student_id" name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php while ($student = $students_result->fetch_assoc()): ?>
                        <option value="<?php echo $student['student_id']; ?>" <?php echo post('student_id') == $student['student_id'] ? 'selected' : ''; ?>>
                            <?php echo e($student['student_card_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Only active students are shown</small>
            </div>
            
            <div class="form-group">
                <label for="section_id" class="required">Select Section</label>
                <select id="section_id" name="section_id" required>
                    <option value="">-- Select Section --</option>
                    <?php while ($section = $sections_result->fetch_assoc()): ?>
                        <option value="<?php echo $section['section_id']; ?>" <?php echo post('section_id') == $section['section_id'] ? 'selected' : ''; ?>>
                            <?php echo e($section['section_name'] . ' - ' . $section['academic_year'] . ' (Semester ' . $section['semester'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Only active sections are shown</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="enrollment_date" class="required">Enrollment Date</label>
                    <input 
                        type="date" 
                        id="enrollment_date" 
                        name="enrollment_date" 
                        required
                        value="<?php echo post('enrollment_date', date('Y-m-d')); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="status" class="required">Status</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo post('status') == 'active' || post('status') == '' ? 'selected' : ''; ?>>Active</option>
                        <option value="dropped" <?php echo post('status') == 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                        <option value="completed" <?php echo post('status') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    Enroll Student
                </button>
                <a href="list_enrollments.php" class="btn btn-secondary btn-lg">
                    Cancel
                </a>
            </div>
            
        </form>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> Information</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li><strong>Student:</strong> Select from active students only</li>
            <li><strong>Section:</strong> Select from active sections only</li>
            <li><strong>Enrollment Date:</strong> Date when the student joined the section</li>
            <li><strong>Status:</strong> Set to "Active" for current enrollments</li>
            <li>Each student can only be enrolled in a section once</li>
            <li>The section's total student count will be automatically updated</li>
        </ul>
    </div>
</div>

<?php
include '../includes/footer.php';
?>