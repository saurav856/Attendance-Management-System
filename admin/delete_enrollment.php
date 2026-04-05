<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Remove Enrollment";

$error = '';
$enrollment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($enrollment_id == 0) {
    header("Location: list_enrollments.php");
    exit();
}

$sql = "SELECT 
    e.*,
    s.first_name,
    s.last_name,
    s.student_card_id,
    sec.section_name,
    sec.academic_year,
    sec.semester
FROM ENROLLMENTS e
JOIN STUDENTS s ON e.student_id = s.student_id
JOIN SECTIONS sec ON e.section_id = sec.section_id
WHERE e.enrollment_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: list_enrollments.php");
    exit();
}

$enrollment = $result->fetch_assoc();
$stmt->close();

if (isPostRequest() && post('confirm_delete') == 'yes') {
    $conn->begin_transaction();
    
    try {
        $delete_sql = "DELETE FROM ENROLLMENTS WHERE enrollment_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $enrollment_id);
        $delete_stmt->execute();
        
        $update_sql = "UPDATE SECTIONS SET total_students = (
                        SELECT COUNT(*) FROM ENROLLMENTS WHERE section_id = ? AND status = 'active'
                      ) WHERE section_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $enrollment['section_id'], $enrollment['section_id']);
        $update_stmt->execute();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "Enrollment removed successfully!";
        header("Location: list_enrollments.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error removing enrollment: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-user-times"></i> Remove Enrollment</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_enrollments.php">Enrollments</a>
            <span>/</span>
            <span>Remove Enrollment</span>
        </div>
    </div>
    <div>
        <a href="list_enrollments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>

<div class="card">
    <div class="card-header" style="background-color: #fee2e2; border-bottom: 2px solid #ef4444;">
        <h2 class="card-title" style="color: #991b1b;">
            <i class="fas fa-exclamation-triangle"></i> Confirm Enrollment Removal
        </h2>
    </div>
    <div class="card-body">
        
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Warning:</strong> This will remove the student from this section. Any attendance records will remain but the student will no longer be enrolled.
        </div>
        
        <h3 style="margin-bottom: 20px; color: #1f2937;">Enrollment Information</h3>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 200px 1fr; gap: 15px;">
                <div><strong>Enrollment ID:</strong></div>
                <div><?php echo $enrollment['enrollment_id']; ?></div>
                
                <div><strong>Student Card ID:</strong></div>
                <div><span class="badge badge-primary"><?php echo e($enrollment['student_card_id']); ?></span></div>
                
                <div><strong>Student Name:</strong></div>
                <div><?php echo e($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></div>
                
                <div><strong>Section:</strong></div>
                <div><span style="font-size: 18px; font-weight: bold; color: #2563eb;"><?php echo e($enrollment['section_name']); ?></span></div>
                
                <div><strong>Academic Year:</strong></div>
                <div><?php echo e($enrollment['academic_year']); ?></div>
                
                <div><strong>Semester:</strong></div>
                <div>Semester <?php echo $enrollment['semester']; ?></div>
                
                <div><strong>Enrollment Date:</strong></div>
                <div><?php echo formatDate($enrollment['enrollment_date']); ?></div>
                
                <div><strong>Status:</strong></div>
                <div><?php echo getStatusBadge($enrollment['status']); ?></div>
            </div>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Note:</strong> The student's attendance records for this section will be preserved. You can re-enroll the student later if needed.
        </div>
        
        <form method="POST" action="" style="margin-top: 30px;">
            <input type="hidden" name="confirm_delete" value="yes">
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Are you sure you want to remove this enrollment?');">
                <i class="fas fa-trash"></i> Yes, Remove Enrollment
                </button>
                <a href="list_enrollments.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> No, Cancel
                </a>
            </div>
        </form>
        
    </div>
</div>

<?php
include '../includes/footer.php';
?>