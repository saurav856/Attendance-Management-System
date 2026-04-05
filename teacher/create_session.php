<?php
require_once '../config.php';
checkAuth('teacher');
$page_title = "Create Attendance Session";

$error = '';
$success = '';
$teacher_id = $_SESSION['teacher_id'];

if (isPostRequest()) {
    $section_subject_id = intval(post('section_subject_id'));
    $session_date = sanitize(post('session_date'));
    $start_time = sanitize(post('start_time'));
    $end_time = sanitize(post('end_time'));
    $session_type = sanitize(post('session_type'));
    
    if (empty($section_subject_id) || empty($session_date) || empty($start_time) || empty($end_time)) {
        $error = "Please fill in all required fields.";
    } elseif ($start_time >= $end_time) {
        $error = "End time must be after start time.";
    } else {
        $check_sql = "SELECT session_id FROM ATTENDANCE_SESSIONS WHERE section_subject_id = ? AND session_date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $section_subject_id, $session_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "A session already exists for this class on the selected date.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $insert_sql = "INSERT INTO ATTENDANCE_SESSIONS (section_subject_id, teacher_id, session_date, start_time, end_time, session_type) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iissss", $section_subject_id, $teacher_id, $session_date, $start_time, $end_time, $session_type);
            
            if ($insert_stmt->execute()) {
                $session_id = $conn->insert_id;
                $_SESSION['success_message'] = "Session created successfully! You can now mark attendance.";
                header("Location: mark_attendance.php?session_id=" . $session_id);
                exit();
            } else {
                $error = "Error creating session: " . $conn->error;
            }
            
            $insert_stmt->close();
        }
    }
}

$my_classes_sql = "SELECT 
    ss.section_subject_id,
    sec.section_name,
    sub.subject_code,
    sub.subject_name,
    ss.class_time,
    ss.room_number
FROM SECTION_SUBJECTS ss
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
WHERE ss.teacher_id = ?
ORDER BY sec.section_name, sub.subject_code";

$my_classes_stmt = $conn->prepare($my_classes_sql);
$my_classes_stmt->bind_param("i", $teacher_id);
$my_classes_stmt->execute();
$my_classes_result = $my_classes_stmt->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-plus-circle"></i> Create Attendance Session</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Create Session</span>
        </div>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-calendar-plus"></i> Session Information</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            
            <div class="form-group">
                <label for="section_subject_id" class="required">Select Class</label>
                <select id="section_subject_id" name="section_subject_id" required>
                    <option value="">-- Select Section & Subject --</option>
                    <?php while ($class = $my_classes_result->fetch_assoc()): ?>
                        <option value="<?php echo $class['section_subject_id']; ?>" <?php echo post('section_subject_id') == $class['section_subject_id'] ? 'selected' : ''; ?>>
                            <?php echo e($class['section_name'] . ' - ' . $class['subject_code'] . ' (' . $class['subject_name'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Select the section and subject for this session</small>
            </div>
            
            <div class="form-group">
                <label for="session_date" class="required">Session Date</label>
                <input 
                    type="date" 
                    id="session_date" 
                    name="session_date" 
                    required
                    value="<?php echo post('session_date', date('Y-m-d')); ?>"
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
                        value="<?php echo post('start_time', date('H:i')); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="end_time" class="required">End Time</label>
                    <input 
                        type="time" 
                        id="end_time" 
                        name="end_time" 
                        required
                        value="<?php echo post('end_time'); ?>"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="session_type" class="required">Session Type</label>
                <select id="session_type" name="session_type" required>
                    <option value="lecture" <?php echo post('session_type') == 'lecture' || post('session_type') == '' ? 'selected' : ''; ?>>Lecture</option>
                    <option value="lab" <?php echo post('session_type') == 'lab' ? 'selected' : ''; ?>>Lab</option>
                    <option value="tutorial" <?php echo post('session_type') == 'tutorial' ? 'selected' : ''; ?>>Tutorial</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    Create Session & Mark Attendance
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-lg">
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
            <li><strong>Class:</strong> Select the section and subject you are teaching</li>
            <li><strong>Session Date:</strong> Date when the class is conducted</li>
            <li><strong>Start/End Time:</strong> Duration of the class session</li>
            <li><strong>Session Type:</strong> Type of session (lecture, lab, or tutorial)</li>
            <li>After creating the session, you will be redirected to mark attendance</li>
            <li>Only one session per class per date is allowed</li>
        </ul>
    </div>
</div>

<?php
$my_classes_stmt->close();
include '../includes/footer.php';
?>