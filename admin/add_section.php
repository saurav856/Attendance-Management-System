<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Add New Section";

$error = '';
$success = '';

if (isPostRequest()) {
    $section_name = sanitize(post('section_name'));
    $teacher_id = post('teacher_id') ? intval(post('teacher_id')) : null;
    $semester = intval(post('semester'));
    $academic_year = sanitize(post('academic_year'));
    $is_active = post('is_active') ? 1 : 0;
    
    if (empty($section_name) || empty($semester) || empty($academic_year)) {
        $error = "Please fill in all required fields.";
    } elseif ($semester < 1 || $semester > 8) {
        $error = "Semester must be between 1 and 8.";
    } else {
        $check_sql = "SELECT section_id FROM SECTIONS WHERE section_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $section_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Section name already exists. Please choose a different name.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            if ($teacher_id) {
                $insert_sql = "INSERT INTO SECTIONS (section_name, teacher_id, semester, academic_year, is_active) 
                              VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("siisi", $section_name, $teacher_id, $semester, $academic_year, $is_active);
            } else {
                $insert_sql = "INSERT INTO SECTIONS (section_name, semester, academic_year, is_active) 
                              VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sisi", $section_name, $semester, $academic_year, $is_active);
            }
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Section '" . htmlspecialchars($section_name) . "' created successfully!";
                header("Location: list_sections.php");
                exit();
            } else {
                $error = "Error creating section: " . $conn->error;
            }
            
            $insert_stmt->close();
        }
    }
}

$teachers_sql = "SELECT teacher_id, first_name, last_name FROM TEACHERS ORDER BY first_name, last_name";
$teachers_result = $conn->query($teachers_sql);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-plus-circle"></i> Add New Section</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_sections.php">Sections</a>
            <span>/</span>
            <span>Add New</span>
        </div>
    </div>
    <div>
        <a href="list_sections.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-layer-group"></i> Section Information</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="section_name" class="required">Section Name</label>
                    <input 
                        type="text" 
                        id="section_name" 
                        name="section_name" 
                        placeholder="Enter section name (e.g., I1, I2, A1)" 
                        required
                        value="<?php echo post('section_name'); ?>"
                    >
                    <small class="text-muted">Must be unique across the system</small>
                </div>
                
                <div class="form-group">
                    <label for="teacher_id">Class Teacher (Optional)</label>
                    <select id="teacher_id" name="teacher_id">
                        <option value="">-- No Class Teacher --</option>
                        <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['teacher_id']; ?>" <?php echo post('teacher_id') == $teacher['teacher_id'] ? 'selected' : ''; ?>>
                                <?php echo e($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="text-muted">Teacher responsible for this section</small>
                </div>
            </div>
            
            <div class="form-row">
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
                </div>
                
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
                    <small class="text-muted">Format: YYYY-YYYY (e.g., 2024-2025)</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" checked>
                    Section is Active
                </label>
                <small class="text-muted" style="display: block; margin-left: 25px;">Active sections can have enrollments and attendance sessions</small>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    Create Section
                </button>
                <a href="list_sections.php" class="btn btn-secondary btn-lg">
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
            <li><strong>Section Name:</strong> Unique identifier for the class (e.g., I1, I2, A1, B2)</li>
            <li><strong>Class Teacher:</strong> Optional - Teacher who oversees this section</li>
            <li><strong>Semester:</strong> Current semester level (1-8)</li>
            <li><strong>Academic Year:</strong> The academic year this section belongs to</li>
            <li>After creating the section:
                <ul style="margin-top: 10px;">
                    <li>Enroll students from the <a href="list_enrollments.php">Enrollments</a> page</li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<?php
include '../includes/footer.php';
?>