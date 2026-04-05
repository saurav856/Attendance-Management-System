<?php
/**
 * Teacher - Edit Attendance (UPDATE)
 * Edit attendance records for a specific session
 */

require_once '../config.php';
checkAuth('teacher');
$page_title = "Edit Attendance";

$error = '';
$success = '';
$teacher_id = $_SESSION['teacher_id'];
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id == 0) {
    header("Location: list_session.php");
    exit();
}

// Fetch session information
$session_sql = "SELECT 
    ats.*,
    sec.section_name,
    sub.subject_code,
    sub.subject_name
FROM ATTENDANCE_SESSIONS ats
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
WHERE ats.session_id = ? AND ats.teacher_id = ?";

$session_stmt = $conn->prepare($session_sql);
$session_stmt->bind_param("ii", $session_id, $teacher_id);
$session_stmt->execute();
$session_result = $session_stmt->get_result();

if ($session_result->num_rows == 0) {
    header("Location: list_session.php");
    exit();
}

$session = $session_result->fetch_assoc();
$session_stmt->close();

// Handle form submission
if (isPostRequest()) {
    $attendance_data = post('attendance', []);
    
    if (empty($attendance_data)) {
        $error = "Please mark attendance for at least one student.";
    } else {
        $conn->begin_transaction();
        
        try {
            foreach ($attendance_data as $student_id => $status) {
                // Check if attendance record exists
                $check_sql = "SELECT attendance_id FROM ATTENDANCE WHERE session_id = ? AND student_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $session_id, $student_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing record
                    $update_sql = "UPDATE ATTENDANCE SET status = ?, marked_at = NOW() WHERE session_id = ? AND student_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("sii", $status, $session_id, $student_id);
                    $update_stmt->execute();
                } else {
                    // Insert new record
                    $insert_sql = "INSERT INTO ATTENDANCE (session_id, student_id, status) VALUES (?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iis", $session_id, $student_id, $status);
                    $insert_stmt->execute();
                }
                
                $check_stmt->close();
            }
            
            $conn->commit();
            $success = "Attendance updated successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating attendance: " . $e->getMessage();
        }
    }
}

// Fetch students with current attendance status
$students_sql = "SELECT 
    s.student_id,
    s.student_card_id,
    s.first_name,
    s.last_name,
    a.status as current_status,
    a.marked_at
FROM ENROLLMENTS e
JOIN STUDENTS s ON e.student_id = s.student_id
JOIN SECTION_SUBJECTS ss ON e.section_id = ss.section_id
LEFT JOIN ATTENDANCE a ON s.student_id = a.student_id AND a.session_id = ?
WHERE ss.section_subject_id = ? AND e.status = 'active'
ORDER BY s.last_name, s.first_name";

$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("ii", $session_id, $session['section_subject_id']);
$students_stmt->execute();
$students_result = $students_stmt->get_result();

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-edit"></i> Edit Attendance</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_session.php">Sessions</a>
            <span>/</span>
            <span>Edit Attendance</span>
        </div>
    </div>
    <div>
        <a href="list_session.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Sessions
        </a>
        <a href="view_attendance.php?session_id=<?php echo $session_id; ?>" class="btn btn-info">
            <i class="fas fa-eye"></i> View Only
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<!-- Session Information Card -->
<div class="card">
    <div class="card-header" style="background-color: #dbeafe;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0; color: #1e40af;">
                    <i class="fas fa-chalkboard"></i> <?php echo e($session['section_name']); ?> - <?php echo e($session['subject_code']); ?>
                </h2>
                <p style="margin: 5px 0 0 0; color: #3b82f6;"><?php echo e($session['subject_name']); ?></p>
            </div>
            <div style="text-align: right;">
                <div><strong>Date:</strong> <?php echo formatDate($session['session_date']); ?></div>
                <div><strong>Time:</strong> <?php echo formatTime($session['start_time']) . ' - ' . formatTime($session['end_time']); ?></div>
                <div><strong>Type:</strong> <span class="badge badge-info"><?php echo ucfirst($session['session_type']); ?></span></div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php if ($students_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Card ID</th>
                                <th>Student Name</th>
                                <th>Current Status</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo e($student['student_card_id']); ?></strong></td>
                                    <td><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td>
                                        <?php if ($student['current_status']): ?>
                                            <?php echo getStatusBadge($student['current_status']); ?>
                                            <br><small class="text-muted">Marked: <?php echo formatDateTime($student['marked_at']); ?></small>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not Marked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="radio" name="attendance[<?php echo $student['student_id']; ?>]" value="present" 
                                            <?php echo $student['current_status'] == 'present' ? 'checked' : ''; ?> required>
                                    </td>
                                    <td>
                                        <input type="radio" name="attendance[<?php echo $student['student_id']; ?>]" value="absent" 
                                            <?php echo $student['current_status'] == 'absent' ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="radio" name="attendance[<?php echo $student['student_id']; ?>]" value="late" 
                                            <?php echo $student['current_status'] == 'late' ? 'checked' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Update Attendance
                    </button>
                    <button type="button" class="btn btn-success" onclick="markAllPresent()">
                        <i class="fas fa-check-double"></i> Mark All Present
                    </button>
                    <a href="list_session.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Students Enrolled</h3>
                    <p>No students are enrolled in this section.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Information Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> Editing Attendance</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li>You can modify the attendance status for any student in this session</li>
            <li>The "Current Status" column shows the previously marked attendance</li>
            <li>Changes will update the marked timestamp to the current time</li>
            <li>All students must have a status selected to save</li>
        </ul>
    </div>
</div>

<script>
// Mark all students as present
function markAllPresent() {
    const radios = document.querySelectorAll('input[type="radio"][value="present"]');
    radios.forEach(radio => radio.checked = true);
}
</script>

<?php
$students_stmt->close();
include '../includes/footer.php';
?>