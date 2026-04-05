<?php
require_once '../config.php';
checkAuth('student');
$page_title = "Mark Attendance";

$error = '';
$success = '';
$student_id = $_SESSION['student_id'];

$student_sql = "SELECT student_card_id FROM STUDENTS WHERE student_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student_data = $student_result->fetch_assoc();
$student_stmt->close();

if (isPostRequest()) {
    $card_id = sanitize(post('card_id'));
    
    if (empty($card_id)) {
        $error = "Please enter your student card ID.";
    } elseif ($card_id !== $student_data['student_card_id']) {
        $error = "Invalid card ID. Please enter your correct student card ID.";
    } else {
        $active_sessions_sql = "SELECT 
            ats.session_id,
            ats.section_subject_id,
            ats.session_date,
            ats.start_time,
            ats.end_time,
            sec.section_name,
            sub.subject_code,
            sub.subject_name
        FROM ATTENDANCE_SESSIONS ats
        JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
        JOIN SECTIONS sec ON ss.section_id = sec.section_id
        JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
        JOIN ENROLLMENTS e ON sec.section_id = e.section_id
        WHERE e.student_id = ? 
        AND e.status = 'active'
        AND ats.session_date = CURDATE()
        AND ats.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM ATTENDANCE a 
            WHERE a.session_id = ats.session_id 
            AND a.student_id = ?
        )";
        
        $active_sessions_stmt = $conn->prepare($active_sessions_sql);
        $active_sessions_stmt->bind_param("ii", $student_id, $student_id);
        $active_sessions_stmt->execute();
        $active_sessions_result = $active_sessions_stmt->get_result();
        
        if ($active_sessions_result->num_rows == 0) {
            $error = "No active sessions available for attendance or you have already marked attendance for today's sessions.";
        } else {
            $marked_count = 0;
            
            while ($session = $active_sessions_result->fetch_assoc()) {
                $insert_sql = "INSERT INTO ATTENDANCE (session_id, student_id, status, student_card_id) 
                              VALUES (?, ?, 'present', ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iis", $session['session_id'], $student_id, $card_id);
                
                if ($insert_stmt->execute()) {
                    $marked_count++;
                }
                $insert_stmt->close();
            }
            
            if ($marked_count > 0) {
                $success = "Attendance marked successfully for $marked_count session(s)!";
            } else {
                $error = "Failed to mark attendance. Please try again.";
            }
        }
        
        $active_sessions_stmt->close();
    }
}

$todays_sessions_sql = "SELECT 
    ats.session_id,
    ats.session_date,
    ats.start_time,
    ats.end_time,
    sec.section_name,
    sub.subject_code,
    sub.subject_name,
    a.status,
    a.marked_at
FROM ATTENDANCE_SESSIONS ats
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
JOIN ENROLLMENTS e ON sec.section_id = e.section_id
LEFT JOIN ATTENDANCE a ON ats.session_id = a.session_id AND a.student_id = ?
WHERE e.student_id = ? 
AND e.status = 'active'
AND ats.session_date = CURDATE()
ORDER BY ats.start_time";

$todays_sessions_stmt = $conn->prepare($todays_sessions_sql);
$todays_sessions_stmt->bind_param("ii", $student_id, $student_id);
$todays_sessions_stmt->execute();
$todays_sessions_result = $todays_sessions_stmt->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-qrcode"></i> Mark Attendance</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Mark Attendance</span>
        </div>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<div class="card">
    <div class="card-header" style="background-color: #dbeafe;">
        <h2 class="card-title" style="color: #1e40af;">
            Enter Your Student Card ID
        </h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-group">
                <label for="card_id" class="required">Student Card ID</label>
                <input 
                    type="text" 
                    id="card_id" 
                    name="card_id" 
                    placeholder="Enter your card ID to mark attendance" 
                    required
                    autofocus
                    style="font-size: 18px; padding: 15px;"
                >
                <small class="text-muted">Your card ID: <strong><?php echo e($student_data['student_card_id']); ?></strong></small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-check-circle"></i> Mark Attendance for Today's Sessions
            </button>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-calendar-day"></i> Today's Sessions</h2>
    </div>
    <div class="card-body">
        <?php if ($todays_sessions_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="mobile-cards">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Marked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($session = $todays_sessions_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Column Name"><?php echo formatTime($session['start_time']) . ' - ' . formatTime($session['end_time']); ?></td>
                                <td data-label="Column Name"><span class="badge badge-primary"><?php echo e($session['section_name']); ?></span></td>
                                <td data-label="Column Name">
                                    <strong><?php echo e($session['subject_code']); ?></strong><br>
                                    <small><?php echo e($session['subject_name']); ?></small>
                                </td>
                                <td data-label="Column Name">
                                    <?php if ($session['status']): ?>
                                        <?php echo getStatusBadge($session['status']); ?>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Not Marked</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Column Name">
                                    <?php if ($session['marked_at']): ?>
                                        <?php echo formatDateTime($session['marked_at']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Sessions Today</h3>
                <p>You have no scheduled sessions today.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> How to Mark Attendance</h2>
    </div>
    <div class="card-body">
        <ol style="line-height: 2; padding-left: 20px;">
            <li>Enter your student card ID in the field above</li>
            <li>Click "Mark Attendance" button</li>
            <li>Your attendance will be marked for all active sessions today</li>
            <li>You can only mark attendance once per session</li>
            <li>Make sure to mark attendance during the class time</li>
        </ol>
        <div class="alert alert-warning" style="margin-top: 15px;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Important:</strong> You can only mark attendance for sessions that are active today. Past sessions cannot be marked by students.
        </div>
    </div>
</div>

<?php
$todays_sessions_stmt->close();
include '../includes/footer.php';
?>