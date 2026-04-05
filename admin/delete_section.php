<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Delete Section";

$error = '';
$section_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($section_id == 0) {
    header("Location: list_sections.php");
    exit();
}

$sql = "SELECT 
    s.*,
    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
FROM SECTIONS s
LEFT JOIN TEACHERS t ON s.teacher_id = t.teacher_id
WHERE s.section_id = ?";

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

$enrollments_count = 0;
$subjects_count = 0;

$enrollments_sql = "SELECT COUNT(*) as count FROM ENROLLMENTS WHERE section_id = ?";
$enrollments_stmt = $conn->prepare($enrollments_sql);
$enrollments_stmt->bind_param("i", $section_id);
$enrollments_stmt->execute();
$enrollments_result = $enrollments_stmt->get_result();
$enrollments_count = $enrollments_result->fetch_assoc()['count'];
$enrollments_stmt->close();

$subjects_sql = "SELECT COUNT(*) as count FROM SECTION_SUBJECTS WHERE section_id = ?";
$subjects_stmt = $conn->prepare($subjects_sql);
$subjects_stmt->bind_param("i", $section_id);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();
$subjects_count = $subjects_result->fetch_assoc()['count'];
$subjects_stmt->close();

if (isPostRequest() && post('confirm_delete') == 'yes') {
    $delete_sql = "DELETE FROM SECTIONS WHERE section_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $section_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Section '" . htmlspecialchars($section['section_name']) . "' has been deleted successfully!";
        header("Location: list_sections.php");
        exit();
    } else {
        $error = "Error deleting section: " . $conn->error;
    }
    
    $delete_stmt->close();
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-trash"></i> Delete Section</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_sections.php">Sections</a>
            <span>/</span>
            <span>Delete Section</span>
        </div>
    </div>
    <div>
        <a href="list_sections.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>

<div class="card">
    <div class="card-header" style="background-color: #fee2e2; border-bottom: 2px solid #ef4444;">
        <h2 class="card-title" style="color: #991b1b;">
            <i class="fas fa-exclamation-triangle"></i> Confirm Section Deletion
        </h2>
    </div>
    <div class="card-body">
        
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Warning:</strong> This action cannot be undone! Deleting this section will permanently remove all enrollments and subject assignments.
        </div>
        
        <h3 style="margin-bottom: 20px; color: #1f2937;">Section Information</h3>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 200px 1fr; gap: 15px;">
                <div><strong>Section ID:</strong></div>
                <div><?php echo $section['section_id']; ?></div>
                
                <div><strong>Section Name:</strong></div>
                <div><span style="font-size: 18px; font-weight: bold; color: #2563eb;"><?php echo e($section['section_name']); ?></span></div>
                
                <div><strong>Class Teacher:</strong></div>
                <div><?php echo $section['teacher_name'] ? e($section['teacher_name']) : '<span class="text-muted">Not assigned</span>'; ?></div>
                
                <div><strong>Semester:</strong></div>
                <div>Semester <?php echo $section['semester']; ?></div>
                
                <div><strong>Academic Year:</strong></div>
                <div><?php echo e($section['academic_year']); ?></div>
                
                <div><strong>Total Students:</strong></div>
                <div><?php echo $section['total_students']; ?></div>
                
                <div><strong>Status:</strong></div>
                <div><?php echo $section['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'; ?></div>
            </div>
        </div>
        
        <?php if ($enrollments_count > 0 || $subjects_count > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-database"></i> 
                <strong>Related Data to be Deleted:</strong><br>
                <ul style="margin: 10px 0 0 20px;">
                    <?php if ($enrollments_count > 0): ?>
                        <li><strong><?php echo $enrollments_count; ?></strong> student enrollment(s) will be permanently deleted</li>
                    <?php endif; ?>
                    <?php if ($subjects_count > 0): ?>
                        <li><strong><?php echo $subjects_count; ?></strong> subject assignment(s) will be permanently deleted</li>
                    <?php endif; ?>
                </ul>
                <p style="margin-top: 15px; margin-bottom: 0;">
                    <i class="fas fa-info-circle"></i> All this data will be <strong>permanently lost</strong> and cannot be recovered.
                </p>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                This section has no enrollments or subject assignments. The section can be safely deleted.
            </div>
        <?php endif; ?>
        
        <?php if ($enrollments_count > 0): ?>
            <?php
            $students_sql = "SELECT 
                s.first_name,
                s.last_name,
                s.student_card_id,
                e.enrollment_date,
                e.status
            FROM ENROLLMENTS e
            JOIN STUDENTS s ON e.student_id = s.student_id
            WHERE e.section_id = ?
            ORDER BY s.last_name, s.first_name";
            
            $students_stmt = $conn->prepare($students_sql);
            $students_stmt->bind_param("i", $section_id);
            $students_stmt->execute();
            $students_result = $students_stmt->get_result();
            ?>
            
            <div style="background-color: #dbeafe; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2563eb;">
                <h4 style="margin-bottom: 15px; color: #1e40af;">
                    <i class="fas fa-users"></i> Enrolled Students
                </h4>
                <table style="width: 100%; font-size: 14px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #93c5fd;">
                            <th style="padding: 8px; text-align: left;">Card ID</th>
                            <th style="padding: 8px; text-align: left;">Student Name</th>
                            <th style="padding: 8px; text-align: left;">Enrolled Date</th>
                            <th style="padding: 8px; text-align: left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $students_result->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #bfdbfe;">
                                <td style="padding: 8px;"><?php echo e($student['student_card_id']); ?></td>
                                <td style="padding: 8px;"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td style="padding: 8px;"><?php echo formatDate($student['enrollment_date']); ?></td>
                                <td style="padding: 8px;"><?php echo getStatusBadge($student['status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php $students_stmt->close(); ?>
        <?php endif; ?>
        
        <form method="POST" action="" style="margin-top: 30px;">
            <input type="hidden" name="confirm_delete" value="yes">
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Are you absolutely sure you want to delete this section?\n\nThis will permanently delete:\n- Section information\n- <?php echo $enrollments_count; ?> enrollment(s)\n- <?php echo $subjects_count; ?> subject assignment(s)\n\nThis action CANNOT be undone!');">
                    <i class="fas fa-trash"></i> Yes, Delete Section
                </button>
                <a href="list_sections.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> No, Cancel
                </a>
            </div>
        </form>
        
    </div>
</div>

<?php
include '../includes/footer.php';
?>