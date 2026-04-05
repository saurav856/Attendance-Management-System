<?php
/**
 * Teacher - Edit Session (UPDATE)
 * Edit attendance session details
 */

require_once '../config.php';
checkAuth('teacher');
$page_title = "Edit Attendance Session";

$error = '';
$success = '';
$teacher_id = $_SESSION['teacher_id'];
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id == 0) {
    header("Location: list_session.php");
    exit();
}

// Fetch session information
$sql = "SELECT 
    ats.*,
    sec.section_name,
    sub.subject_code,
    sub.subject_name
FROM ATTENDANCE_SESSIONS ats
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
WHERE ats.session_id = ? AND ats.teacher_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $session_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: list_session.php");
    exit();
}

$session = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if (isPostRequest()) {
    $session_date = sanitize(post('session_date'));
    $start_time = sanitize(post('start_time'));
    $end_time = sanitize(post('end_time'));
    $session_type = sanitize(post('session_type'));
    
    if (empty($session_date) || empty($start_time) || empty($end_time)) {
        $error = "Please fill in all required fields.";
    } elseif ($start_time >= $end_time) {
        $error = "End time must be after start time.";
    } else {
        // Check if another session exists for the same class on this date (excluding current session)
        $check_sql = "SELECT session_id FROM ATTENDANCE_SESSIONS 
                     WHERE section_subject_id = ? AND session_date = ? AND session_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isi", $session['section_subject_id'], $session_date, $session_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "A session already exists for this class on the selected date.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $update_sql = "UPDATE ATTENDANCE_SESSIONS SET 
                          session_date = ?, 
                          start_time = ?, 
                          end_time = ?, 
                          session_type = ?
                          WHERE session_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssi", $session_date, $start_time, $end_time, $session_type, $session_id);
            
            if ($update_stmt->execute()) {
                $success = "Session updated successfully!";
                
                // Refresh session data
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $session_id, $teacher_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $session = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Error updating session: " . $conn->error;
            }
            
            $update_stmt->close();
        }
    }
}

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-edit"></i> Edit Attendance Session</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_session.php">Sessions</a>
            <span>/</span>
            <span>Edit Session</span>
        </div>
    </div>
    <div>
        <a href="list_session.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Sessions
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<!-- Edit Session Form -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-calendar-edit"></i> Session Information</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            
            <!-- Read-only Class Information -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Section and Subject cannot be changed. Create a new session if needed.
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Section</label>
                    <input 
                        type="text" 
                        value="<?php echo e($session['section_name']); ?>" 
                        disabled
                        style="background-color: #f3f4f6; cursor: not-allowed;"
                    >
                </div>
                
                <div class="form-group">
                    <label>Subject</label>
                    <input 
                        type="text" 
                        value="<?php echo e($session['subject_code'] . ' - ' . $session['subject_name']); ?>" 
                        disabled
                        style="background-color: #f3f4f6; cursor: not-allowed;"
                    >
                </div>
            </div>
            
            <!-- Editable Fields -->
            <div class="form-group">
                <label for="session_date" class="required">Session Date</label>
                <input 
                    type="date" 
                    id="session_date" 
                    name="session_date" 
                    required
                    value="<?php echo e($session['session_date']); ?>"
                >
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="start_time" class="required">Start Time</label>
                    <input 
                        type="time" 
                        id="start_time" 
                        name="start_time" 
                        required
                        value="<?php echo e($session['start_time']); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="end_time" class="required">End Time</label>
                    <input 
                        type="time" 
                        id="end_time" 
                        name="end_time" 
                        required
                        value="<?php echo e($session['end_time']); ?>"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="session_type" class="required">Session Type</label>
                <select id="session_type" name="session_type" required>
                    <option value="lecture" <?php echo $session['session_type'] == 'lecture' ? 'selected' : ''; ?>>Lecture</option>
                    <option value="lab" <?php echo $session['session_type'] == 'lab' ? 'selected' : ''; ?>>Lab</option>
                    <option value="tutorial" <?php echo $session['session_type'] == 'tutorial' ? 'selected' : ''; ?>>Tutorial</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Update Session
                </button>
                <a href="list_session.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
        </form>
    </div>
</div>

<!-- Session Statistics -->
<?php
$attendance_count_sql = "SELECT COUNT(*) as count FROM ATTENDANCE WHERE session_id = ?";
$attendance_count_stmt = $conn->prepare($attendance_count_sql);
$attendance_count_stmt->bind_param("i", $session_id);
$attendance_count_stmt->execute();
$attendance_count_result = $attendance_count_stmt->get_result();
$attendance_count = $attendance_count_result->fetch_assoc()['count'];
$attendance_count_stmt->close();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-bar"></i> Session Statistics</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #2563eb;"><?php echo $attendance_count; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Attendance Records</div>
            </div>
        </div>
        
        <?php if ($attendance_count > 0): ?>
            <div class="alert alert-warning" style="margin-top: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> This session has <?php echo $attendance_count; ?> attendance record(s). Changing the date might affect reporting accuracy.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Information Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> Information</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li><strong>Session Date:</strong> Date when the class was/will be conducted</li>
            <li><strong>Start/End Time:</strong> Duration of the class session</li>
            <li><strong>Session Type:</strong> Type of session (lecture, lab, or tutorial)</li>
            <li>Only one session per class per date is allowed</li>
            <li>Existing attendance records will remain unchanged when you edit session details</li>
        </ul>
    </div>
</div>

<?php
include '../includes/footer.php';
?>