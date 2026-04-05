<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Edit Subject";

$error = '';
$success = '';

$subject_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($subject_id == 0) {
    header("Location: list_subjects.php");
    exit();
}

$sql = "SELECT * FROM SUBJECTS WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: list_subjects.php");
    exit();
}

$subject = $result->fetch_assoc();
$stmt->close();

if (isPostRequest()) {
    $subject_code = sanitize(post('subject_code'));
    $subject_name = sanitize(post('subject_name'));
    $credit_hours = intval(post('credit_hours'));
    $description = sanitize(post('description'));
    
    if (empty($subject_code) || empty($subject_name) || empty($credit_hours)) {
        $error = "Please fill in all required fields.";
    } elseif ($credit_hours < 1 || $credit_hours > 10) {
        $error = "Credit hours must be between 1 and 10.";
    } else {
        $check_sql = "SELECT subject_id FROM SUBJECTS WHERE subject_code = ? AND subject_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $subject_code, $subject_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Subject code already exists. Please use a different code.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $update_sql = "UPDATE SUBJECTS SET 
                          subject_code = ?, 
                          subject_name = ?, 
                          credit_hours = ?, 
                          description = ?
                          WHERE subject_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssisi", $subject_code, $subject_name, $credit_hours, $description, $subject_id);
            
            if ($update_stmt->execute()) {
                $success = "Subject updated successfully!";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $subject_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $subject = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Error updating subject: " . $conn->error;
            }
            
            $update_stmt->close();
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-edit"></i> Edit Subject</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_subjects.php">Subjects</a>
            <span>/</span>
            <span>Edit Subject</span>
        </div>
    </div>
    <div>
        <a href="list_subjects.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-book"></i> Edit Subject Information</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="subject_code" class="required">Subject Code</label>
                    <input 
                        type="text" 
                        id="subject_code" 
                        name="subject_code" 
                        placeholder="e.g., CS101" 
                        required
                        value="<?php echo e($subject['subject_code']); ?>"
                    >
                    <small class="text-muted">Unique identifier for the subject</small>
                </div>
                
                <div class="form-group">
                    <label for="credit_hours" class="required">Credit Hours</label>
                    <input 
                        type="number" 
                        id="credit_hours" 
                        name="credit_hours" 
                        placeholder="Enter credit hours" 
                        required
                        min="1"
                        max="10"
                        value="<?php echo e($subject['credit_hours']); ?>"
                    >
                    <small class="text-muted">Number of credit hours (1-10)</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="subject_name" class="required">Subject Name</label>
                <input 
                    type="text" 
                    id="subject_name" 
                    name="subject_name" 
                    placeholder="Enter full subject name" 
                    required
                    value="<?php echo e($subject['subject_name']); ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea 
                    id="description" 
                    name="description" 
                    placeholder="Enter subject description"
                    rows="4"
                ><?php echo e($subject['description']); ?></textarea>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    Update Subject
                </button>
                <a href="list_subjects" class="btn btn-secondary btn-lg">
                    Cancel
                </a>
            </div>
            
        </form>
    </div>
</div>

<?php
$stats_sql = "SELECT 
    COUNT(DISTINCT ss.section_id) as sections_count,
    COUNT(DISTINCT ss.teacher_id) as teachers_count
FROM SECTION_SUBJECTS ss
WHERE ss.subject_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $subject_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-bar"></i> Subject Statistics</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #2563eb;"><?php echo $stats['sections_count']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Assigned to Sections</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #fef3c7; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo $stats['teachers_count']; ?></div>
                <div style="color: #92400e; font-weight: 500;">Teaching Teachers</div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>