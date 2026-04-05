<?php
/**
 * Student - My Schedule
 * Display class schedule (timetable) for the logged-in student
 */

require_once '../config.php';
checkAuth('student');
$page_title = "My Schedule";

$student_id = $_SESSION['student_id'];

// Get student's enrolled sections
$sections_sql = "SELECT DISTINCT sec.section_id, sec.section_name, sec.academic_year, sec.semester
                FROM ENROLLMENTS e
                JOIN SECTIONS sec ON e.section_id = sec.section_id
                WHERE e.student_id = ? AND e.status = 'active'";
$sections_stmt = $conn->prepare($sections_sql);
$sections_stmt->bind_param("i", $student_id);
$sections_stmt->execute();
$sections_result = $sections_stmt->get_result();

// Get class schedule (subjects assigned to enrolled sections)
$schedule_sql = "SELECT 
    ss.section_subject_id,
    ss.class_time,
    ss.room_number,
    sec.section_name,
    sub.subject_code,
    sub.subject_name,
    sub.credit_hours,
    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
    t.email as teacher_email
FROM ENROLLMENTS e
JOIN SECTIONS sec ON e.section_id = sec.section_id
JOIN SECTION_SUBJECTS ss ON sec.section_id = ss.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
JOIN TEACHERS t ON ss.teacher_id = t.teacher_id
WHERE e.student_id = ? AND e.status = 'active'
ORDER BY ss.class_time ASC, sub.subject_code ASC";

$schedule_stmt = $conn->prepare($schedule_sql);
$schedule_stmt->bind_param("i", $student_id);
$schedule_stmt->execute();
$schedule_result = $schedule_stmt->get_result();

// Get upcoming sessions (next 7 days)
$upcoming_sessions_sql = "SELECT 
    ats.session_id,
    ats.session_date,
    ats.start_time,
    ats.end_time,
    ats.session_type,
    sub.subject_code,
    sub.subject_name,
    sec.section_name,
    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
FROM ATTENDANCE_SESSIONS ats
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
JOIN TEACHERS t ON ats.teacher_id = t.teacher_id
JOIN ENROLLMENTS e ON sec.section_id = e.section_id
WHERE e.student_id = ? 
AND e.status = 'active'
AND ats.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
AND ats.is_active = 1
ORDER BY ats.session_date ASC, ats.start_time ASC";

$upcoming_stmt = $conn->prepare($upcoming_sessions_sql);
$upcoming_stmt->bind_param("i", $student_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-calendar-alt"></i> My Class Schedule</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>My Schedule</span>
        </div>
    </div>
</div>

<!-- Enrolled Sections Info -->
<?php if ($sections_result->num_rows > 0): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-layer-group"></i> Enrolled Sections</h2>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php while ($section = $sections_result->fetch_assoc()): ?>
                    <div style="padding: 15px; background-color: #dbeafe; border-radius: 8px; border-left: 4px solid #2563eb;">
                        <div style="font-size: 18px; font-weight: bold; color: #1e40af;">
                            <i class="fas fa-folder"></i> <?php echo e($section['section_name']); ?>
                        </div>
                        <div style="color: #3b82f6; margin-top: 5px;">
                            <?php echo e($section['academic_year']); ?> - Semester <?php echo $section['semester']; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Class Schedule / Timetable -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-calendar-week"></i> Class Timetable</h2>
    </div>
    <div class="card-body">
        <?php if ($schedule_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Credit Hours</th>
                            <th>Teacher</th>
                            <th>Room</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($class = $schedule_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if ($class['class_time']): ?>
                                        <strong><i class="fas fa-clock"></i> <?php echo formatTime($class['class_time']); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Not scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo e($class['section_name']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo e($class['subject_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($class['subject_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $class['credit_hours']; ?> hrs</span>
                                </td>
                                <td>
                                    <i class="fas fa-user-tie"></i> <?php echo e($class['teacher_name']); ?>
                                    <?php if ($class['teacher_email']): ?>
                                        <br><small class="text-muted"><?php echo e($class['teacher_email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($class['room_number']): ?>
                                        <i class="fas fa-door-open"></i> <?php echo e($class['room_number']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">TBA</span>
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
                <h3>No Classes Scheduled</h3>
                <p>You are not enrolled in any sections or no classes have been scheduled yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upcoming Sessions (Next 7 Days) -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-calendar-day"></i> Upcoming Sessions (Next 7 Days)</h2>
    </div>
    <div class="card-body">
        <?php if ($upcoming_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($session = $upcoming_result->fetch_assoc()): 
                            $day_of_week = date('l', strtotime($session['session_date']));
                            $is_today = $session['session_date'] == date('Y-m-d');
                        ?>
                            <tr <?php echo $is_today ? 'style="background-color: #fef3c7;"' : ''; ?>>
                                <td>
                                    <strong><?php echo formatDate($session['session_date']); ?></strong>
                                    <?php if ($is_today): ?>
                                        <span class="badge badge-warning" style="margin-left: 5px;">Today</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $day_of_week; ?></td>
                                <td>
                                    <i class="fas fa-clock"></i> 
                                    <?php echo formatTime($session['start_time']) . ' - ' . formatTime($session['end_time']); ?>
                                </td>
                                <td><span class="badge badge-primary"><?php echo e($session['section_name']); ?></span></td>
                                <td>
                                    <strong><?php echo e($session['subject_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($session['subject_name']); ?></small>
                                </td>
                                <td><span class="badge badge-info"><?php echo ucfirst($session['session_type']); ?></span></td>
                                <td><?php echo e($session['teacher_name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <h3>No Upcoming Sessions</h3>
                <p>No attendance sessions are scheduled for the next 7 days.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Information Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> Schedule Information</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li><strong>Class Timetable:</strong> Shows all subjects you are enrolled in with regular class times</li>
            <li><strong>Upcoming Sessions:</strong> Shows attendance sessions scheduled for the next 7 days</li>
            <li><strong>Today's Sessions:</strong> Highlighted in yellow for easy identification</li>
            <li>Make sure to mark your attendance during or after each class session</li>
            <li>Class times shown are regular scheduled times - actual session times may vary</li>
            <li>Contact your teacher if you have questions about the schedule</li>
        </ul>
    </div>
</div>

<?php
$sections_stmt->close();
$schedule_stmt->close();
$upcoming_stmt->close();
include '../includes/footer.php';
?>