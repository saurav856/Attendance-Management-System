<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Add New Subject";

$error = '';
$success = '';

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
        $check_sql = "SELECT subject_id FROM SUBJECTS WHERE subject_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $subject_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Subject code already exists. Please use a different code.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $insert_sql = "INSERT INTO SUBJECTS (subject_code, subject_name, credit_hours, description) 
                          VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssis", $subject_code, $subject_name, $credit_hours, $description);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Subject '" . htmlspecialchars($subject_name) . "' created successfully!";
                header("Location: list_subjects.php");
                exit();
            } else {
                $error = "Error creating subject: " . $conn->error;
            }
            
            $insert_stmt->close();
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-book-medical"></i> Add New Subject</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_subjects.php">Subjects</a>
            <span>/</span>
            <span>Add New</span>
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
        <h2 class="card-title"><i class="fas fa-book"></i> Subject Information</h2>
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
                        placeholder="e.g., CS101, MATH201" 
                        required
                        value="<?php echo post('subject_code'); ?>"
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
                        value="<?php echo post('credit_hours', '3'); ?>"
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
                    value="<?php echo post('subject_name'); ?>"
                >
                <small class="text-muted">Full name of the subject</small>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea 
                    id="description" 
                    name="description" 
                    placeholder="Enter subject description (optional)"
                    rows="4"
                ><?php echo post('description'); ?></textarea>
                <small class="text-muted">Brief description of the subject content</small>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    Create Subject
                </button>
                <a href="list_subjects.php" class="btn btn-secondary btn-lg">
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
            <li><strong>Subject Code:</strong> Must be unique across all subjects (e.g., CS101, MATH201)</li>
            <li><strong>Subject Name:</strong> Full descriptive name of the subject</li>
            <li><strong>Credit Hours:</strong> Academic credit value (typically 1-6 hours)</li>
            <li><strong>Description:</strong> Optional brief overview of subject content</li>
        </ul>
    </div>
</div>

<?php
include '../includes/footer.php';
?>