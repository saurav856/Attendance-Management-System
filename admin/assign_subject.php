<?php
/**
 * Admin - Assign Subject to Section with Teacher
 * Create a new section-subject-teacher assignment
 */

require_once '../config.php';
checkAuth('admin');
$page_title = "Assign Subject to Section";

$error = '';
$success = '';

// Handle form submission
if (isPostRequest()) {
    $section_id = intval(post('section_id'));
    $subject_id = intval(post('subject_id'));
    $teacher_id = intval(post('teacher_id'));
    $academic_year = sanitize(post('academic_year'));
    $semester = sanitize(post('semester'));
    $class_time = sanitize(post('class_time'));
    $room_number = sanitize(post('room_number'));
    
    // Validation
    if (empty($section_id) || empty($subject_id) || empty($teacher_id) || empty($academic_year) || empty($semester)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if assignment already exists
        $check_sql = "SELECT section_subject_id FROM SECTION_SUBJECTS 
                     WHERE section_id = ? AND subject_id = ? AND academic_year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iis", $section_id, $subject_id, $academic_year);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "This subject is already assigned to the selected section for this academic year.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            // Insert new assignment
            $insert_sql = "INSERT INTO SECTION_SUBJECTS 
                          (section_id, subject_id, teacher_id, academic_year, semester, class_time, room_number) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiissss", $section_id, $subject_id, $teacher_id, $academic_year, $semester, $class_time, $room_number);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Subject assigned to section successfully!";
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Error creating assignment: " . $conn->error;
            }
            
            $insert_stmt->close();
        }
    }
}

// Get all active sections
$sections_sql = "SELECT section_id, section_name, academic_year, semester FROM SECTIONS WHERE is_active = 1 ORDER BY section_name";
$sections_result = $conn->query($sections_sql);

// Get all subjects
$subjects_sql = "SELECT subject_id, subject_code, subject_name, credit_hours FROM SUBJECTS ORDER BY subject_code";
$subjects_result = $conn->query($subjects_sql);

// Get all teachers
$teachers_sql = "SELECT teacher_id, first_name, last_name, department FROM TEACHERS ORDER BY first_name, last_name";
$teachers_result = $conn->query($teachers_sql);

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-link"></i> Assign Subject to Section</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="dashboard.php">Subject Assignments</a>
            <span>/</span>
            <span>Assign Subject</span>
        </div>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<!-- Assignment Form -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chalkboard-teacher"></i> Create Subject Assignment</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            
            <!-- Section Selection -->
            <div class="form-group">
                <label for="section_id" class="required">Select Section</label>
                <select id="section_id" name="section_id" required onchange="updateSectionInfo()">
                    <option value="">-- Select Section --</option>
                    <?php while ($section = $sections_result->fetch_assoc()): ?>
                        <option 
                            value="<?php echo $section['section_id']; ?>" 
                            data-year="<?php echo e($section['academic_year']); ?>"
                            data-semester="<?php echo $section['semester']; ?>"
                            <?php echo post('section_id') == $section['section_id'] ? 'selected' : ''; ?>>
                            <?php echo e($section['section_name'] . ' - ' . $section['academic_year'] . ' (Semester ' . $section['semester'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Choose the class section for this assignment</small>
            </div>
            
            <!-- Subject Selection -->
            <div class="form-group">
                <label for="subject_id" class="required">Select Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">-- Select Subject --</option>
                    <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                        <option value="<?php echo $subject['subject_id']; ?>" <?php echo post('subject_id') == $subject['subject_id'] ? 'selected' : ''; ?>>
                            <?php echo e($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['credit_hours'] . ' hrs)'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Choose the subject to be taught</small>
            </div>
            
            <!-- Teacher Selection -->
            <div class="form-group">
                <label for="teacher_id" class="required">Assign Teacher</label>
                <select id="teacher_id" name="teacher_id" required>
                    <option value="">-- Select Teacher --</option>
                    <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                        <option value="<?php echo $teacher['teacher_id']; ?>" <?php echo post('teacher_id') == $teacher['teacher_id'] ? 'selected' : ''; ?>>
                            <?php echo e($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                            <?php if ($teacher['department']): ?>
                                - <?php echo e($teacher['department']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Choose the teacher who will teach this subject</small>
            </div>
            
            <!-- Academic Year and Semester -->
            <div class="form-row">
                <div class="form-group">
                    <label for="academic_year" class="required">Academic Year</label>
                    <input 
                        type="text" 
                        id="academic_year" 
                        name="academic_year" 
                        placeholder="e.g., 2024-2025" 
                        required
                        pattern="\d{4}-\d{4}"
                        value="<?php echo post('academic_year', getCurrentAcademicYear()); ?>"
                    >
                    <small class="text-muted">Will auto-fill from selected section</small>
                </div>
                
                <div class="form-group">
                    <label for="semester" class="required">Semester</label>
                    <select id="semester" name="semester" required>
                        <option value="">-- Select Semester --</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo post('semester') == $i ? 'selected' : ''; ?>>
                                Semester <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <small class="text-muted">Will auto-fill from selected section</small>
                </div>
            </div>
            
            <!-- Optional: Class Time and Room -->
            <div class="form-row">
                <div class="form-group">
                    <label for="class_time">Regular Class Time (Optional)</label>
                    <input 
                        type="time" 
                        id="class_time" 
                        name="class_time"
                        value="<?php echo post('class_time'); ?>"
                    >
                    <small class="text-muted">Regular scheduled time for this class</small>
                </div>
                
                <div class="form-group">
                    <label for="room_number">Room Number (Optional)</label>
                    <input 
                        type="text" 
                        id="room_number" 
                        name="room_number" 
                        placeholder="e.g., Room 101, Lab A"
                        value="<?php echo post('room_number'); ?>"
                    >
                    <small class="text-muted">Regular classroom for this class</small>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    Assign Subject
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-lg">
                    Cancel
                </a>
            </div>
            
        </form>
    </div>
</div>

<!-- Information Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> Assignment Information</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li><strong>Section:</strong> The class/group of students</li>
            <li><strong>Subject:</strong> The course/subject to be taught</li>
            <li><strong>Teacher:</strong> The instructor assigned to teach this subject to this section</li>
            <li><strong>Academic Year & Semester:</strong> Should match the section's academic year and semester</li>
            <li><strong>Class Time & Room:</strong> Optional but recommended for scheduling purposes</li>
            <li>Each subject can only be assigned once per section per academic year</li>
            <li>After assignment, the teacher can create attendance sessions for this class</li>
            <li>Students enrolled in the section will automatically be part of this subject's attendance</li>
        </ul>
    </div>
</div>

<!-- Quick Stats Card -->
<?php
// Get quick statistics
$total_assignments_sql = "SELECT COUNT(*) as total FROM SECTION_SUBJECTS";
$total_assignments_result = $conn->query($total_assignments_sql);
$total_assignments = $total_assignments_result->fetch_assoc()['total'];
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Current System Overview</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 28px; font-weight: bold; color: #2563eb;"><?php echo $total_assignments; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Total Assignments</div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for auto-fill section info -->
<script>
function updateSectionInfo() {
    const sectionSelect = document.getElementById('section_id');
    const selectedOption = sectionSelect.options[sectionSelect.selectedIndex];
    
    if (selectedOption.value) {
        const academicYear = selectedOption.getAttribute('data-year');
        const semester = selectedOption.getAttribute('data-semester');
        
        document.getElementById('academic_year').value = academicYear;
        document.getElementById('semester').value = semester;
    } else {
        document.getElementById('academic_year').value = '';
        document.getElementById('semester').value = '';
    }
}

// Initialize on page load if section is already selected
document.addEventListener('DOMContentLoaded', function() {
    const sectionSelect = document.getElementById('section_id');
    if (sectionSelect.value) {
        updateSectionInfo();
    }
});
</script>

<?php
include '../includes/footer.php';
?>