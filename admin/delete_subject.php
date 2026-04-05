<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Delete Subject";

$error = '';
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

$assignments_count = 0;

$assignments_sql = "SELECT COUNT(*) as count FROM SECTION_SUBJECTS WHERE subject_id = ?";
$assignments_stmt = $conn->prepare($assignments_sql);
$assignments_stmt->bind_param("i", $subject_id);
$assignments_stmt->execute();
$assignments_result = $assignments_stmt->get_result();
$assignments_count = $assignments_result->fetch_assoc()['count'];
$assignments_stmt->close();

if (isPostRequest() && post('confirm_delete') == 'yes') {
    $delete_sql = "DELETE FROM SUBJECTS WHERE subject_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $subject_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Subject '" . htmlspecialchars($subject['subject_name']) . "' has been deleted successfully!";
        header("Location: list_subjects.php");
        exit();
    } else {
        $error = "Error deleting subject: " . $conn->error;
    }
    
    $delete_stmt->close();
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-trash"></i> Delete Subject</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_subjects.php">Subjects</a>
            <span>/</span>
            <span>Delete Subject</span>
        </div>
    </div>
    <div>
        <a href="list_subjects.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>

<div class="card">
    <div class="card-header" style="background-color: #fee2e2; border-bottom: 2px solid #ef4444;">
        <h2 class="card-title" style="color: #991b1b;">
            <i class="fas fa-exclamation-triangle"></i> Confirm Subject Deletion
        </h2>
    </div>
    <div class="card-body">
        
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Warning:</strong> This action cannot be undone! Deleting this subject will permanently remove all section assignments.
        </div>
        
        <h3 style="margin-bottom: 20px; color: #1f2937;">Subject Information</h3>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 200px 1fr; gap: 15px;">
                <div><strong>Subject ID:</strong></div>
                <div><?php echo $subject['subject_id']; ?></div>
                
                <div><strong>Subject Code:</strong></div>
                <div><span style="font-size: 18px; font-weight: bold; color: #2563eb;"><?php echo e($subject['subject_code']); ?></span></div>
                
                <div><strong>Subject Name:</strong></div>
                <div><?php echo e($subject['subject_name']); ?></div>
                
                <div><strong>Credit Hours:</strong></div>
                <div><?php echo $subject['credit_hours']; ?> hours</div>
                
                <div><strong>Description:</strong></div>
                <div><?php echo $subject['description'] ? e($subject['description']) : '<span class="text-muted">No description</span>'; ?></div>
            </div>
        </div>
        
        <?php if ($assignments_count > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-database"></i> 
                <strong>Related Data to be Deleted:</strong><br>
                <ul style="margin: 10px 0 0 20px;">
                    <li><strong><?php echo $assignments_count; ?></strong> section assignment(s) will be permanently deleted</li>
                    <li>All attendance sessions for this subject will be deleted</li>
                </ul>
                <p style="margin-top: 15px; margin-bottom: 0;">
                    <i class="fas fa-info-circle"></i> All this data will be <strong>permanently lost</strong> and cannot be recovered.
                </p>
            </div>
            
            <?php
            $assignments_list_sql = "SELECT 
                sec.section_name,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                ss.academic_year,
                ss.semester
            FROM SECTION_SUBJECTS ss
            JOIN SECTIONS sec ON ss.section_id = sec.section_id
            JOIN TEACHERS t ON ss.teacher_id = t.teacher_id
            WHERE ss.subject_id = ?
            ORDER BY ss.academic_year DESC, sec.section_name";
            
            $assignments_list_stmt = $conn->prepare($assignments_list_sql);
            $assignments_list_stmt->bind_param("i", $subject_id);
            $assignments_list_stmt->execute();
            $assignments_list_result = $assignments_list_stmt->get_result();
            ?>
            
            <div style="background-color: #fef3c7; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
                <h4 style="margin-bottom: 15px; color: #92400e;">
                    <i class="fas fa-link"></i> Section Assignments
                </h4>
                <table style="width: 100%; font-size: 14px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #fde68a;">
                            <th style="padding: 8px; text-align: left;">Section</th>
                            <th style="padding: 8px; text-align: left;">Teacher</th>
                            <th style="padding: 8px; text-align: left;">Academic Year</th>
                            <th style="padding: 8px; text-align: left;">Semester</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($assignment = $assignments_list_result->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #fde68a;">
                                <td style="padding: 8px;"><?php echo e($assignment['section_name']); ?></td>
                                <td style="padding: 8px;"><?php echo e($assignment['teacher_name']); ?></td>
                                <td style="padding: 8px;"><?php echo e($assignment['academic_year']); ?></td>
                                <td style="padding: 8px;">Semester <?php echo $assignment['semester']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php $assignments_list_stmt->close(); ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                This subject has no section assignments. The subject can be safely deleted.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" style="margin-top: 30px;">
            <input type="hidden" name="confirm_delete" value="yes">
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Are you absolutely sure you want to delete this subject?\n\nThis will permanently delete:\n- Subject information\n- <?php echo $assignments_count; ?> section assignment(s)\n- All related attendance sessions\n\nThis action CANNOT be undone!');">
                    <i class="fas fa-trash"></i> Yes, Delete Subject
                </button>
                <a href="list_subjects.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> No, Cancel
                </a>
            </div>
        </form>
        
    </div>
</div>

<?php
include '../includes/footer.php';
?>