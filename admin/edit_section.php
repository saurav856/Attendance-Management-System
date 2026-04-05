<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Edit Section";

$error = '';
$success = '';

$section_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($section_id == 0) {
    header("Location: list_sections.php");
    exit();
}

$sql = "SELECT * FROM SECTIONS WHERE section_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: list_sections.php");
    exit();
}

$section = $result->fetch_assoc();
$stmt->close();

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
        $check_sql = "SELECT section_id FROM SECTIONS WHERE section_name = ? AND section_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $section_name, $section_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Section name already exists. Please choose a different name.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            if ($teacher_id) {
                $update_sql = "UPDATE SECTIONS SET 
                              section_name = ?, 
                              teacher_id = ?, 
                              semester = ?, 
                              academic_year = ?, 
                              is_active = ?
                              WHERE section_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("siisii", $section_name, $teacher_id, $semester, $academic_year, $is_active, $section_id);
            } else {
                $update_sql = "UPDATE SECTIONS SET 
                              section_name = ?, 
                              teacher_id = NULL, 
                              semester = ?, 
                              academic_year = ?, 
                              is_active = ?
                              WHERE section_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sisii", $section_name, $semester, $academic_year, $is_active, $section_id);
            }
            
            if ($update_stmt->execute()) {
                $success = "Section updated successfully!";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $section_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $section = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Error updating section: " . $conn->error;
            }
            
            $update_stmt->close();
        }
    }
}

$teachers_sql = "SELECT teacher_id, first_name, last_name FROM TEACHERS ORDER BY first_name, last_name";
$teachers_result = $conn->query($teachers_sql);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-edit"></i> Edit Section</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_sections.php">Sections</a>
            <span>/</span>
            <span>Edit Section</span>
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
        <h2 class="card-title">
            <i class="fas fa-layer-group"></i> Edit Section Information
        </h2>
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
                        placeholder="Enter section name" 
                        required
                        value="<?php echo e($section['section_name']); ?>"
                    >
                    <small class="text-muted">Must be unique across the system</small>
                </div>
                
                <div class="form-group">
                    <label for="teacher_id">Class Teacher (Optional)</label>
                    <select id="teacher_id" name="teacher_id">
                        <option value="">-- No Class Teacher --</option>
                        <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['teacher_id']; ?>" 
                                <?php echo $section['teacher_id'] == $teacher['teacher_id'] ? 'selected' : ''; ?>>
                                <?php echo e($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="semester" class="required">Semester</label>
                    <select id="semester" name="semester" required>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $section['semester'] == $i ? 'selected' : ''; ?>>
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
                        value="<?php echo e($section['academic_year']); ?>"
                    >
                    <small class="text-muted">Format: YYYY-YYYY</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo $section['is_active'] ? 'checked' : ''; ?>>
                    Section is Active
                </label>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    Update Section
                </button>
                <a href="list_sections.php" class="btn btn-secondary btn-lg">
                    Cancel
                </a>
            </div>
            
        </form>
    </div>
</div>

<?php
$stats_sql = "SELECT 
    COUNT(DISTINCT e.enrollment_id) as enrolled_students,
    COUNT(DISTINCT ss.section_subject_id) as assigned_subjects
FROM SECTIONS s
LEFT JOIN ENROLLMENTS e ON s.section_id = e.section_id AND e.status = 'active'
LEFT JOIN SECTION_SUBJECTS ss ON s.section_id = ss.section_id
WHERE s.section_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $section_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-bar"></i> Section Statistics</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $stats['enrolled_students']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Enrolled Students</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #2563eb;"><?php echo $stats['assigned_subjects']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Assigned Subjects</div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>