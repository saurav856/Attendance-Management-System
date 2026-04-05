<?php
require_once '../config.php';
checkAuth('teacher');
$page_title = "Teacher Dashboard";

$teacher_id = $_SESSION['teacher_id'];

$assigned_subjects_sql = "SELECT COUNT(DISTINCT ss.section_subject_id) as count FROM SECTION_SUBJECTS ss WHERE ss.teacher_id = ?";
$assigned_subjects_stmt = $conn->prepare($assigned_subjects_sql);
$assigned_subjects_stmt->bind_param("i", $teacher_id);
$assigned_subjects_stmt->execute();
$assigned_subjects_count = $assigned_subjects_stmt->get_result()->fetch_assoc()['count'];
$assigned_subjects_stmt->close();

$total_sessions_sql = "SELECT COUNT(*) as count FROM ATTENDANCE_SESSIONS WHERE teacher_id = ?";
$total_sessions_stmt = $conn->prepare($total_sessions_sql);
$total_sessions_stmt->bind_param("i", $teacher_id);
$total_sessions_stmt->execute();
$total_sessions_count = $total_sessions_stmt->get_result()->fetch_assoc()['count'];
$total_sessions_stmt->close();

$today_sessions_sql = "SELECT COUNT(*) as count FROM ATTENDANCE_SESSIONS WHERE teacher_id = ? AND session_date = CURDATE()";
$today_sessions_stmt = $conn->prepare($today_sessions_sql);
$today_sessions_stmt->bind_param("i", $teacher_id);
$today_sessions_stmt->execute();
$today_sessions_count = $today_sessions_stmt->get_result()->fetch_assoc()['count'];
$today_sessions_stmt->close();

$my_classes_sql = "SELECT 
    ss.section_subject_id,
    sec.section_name,
    sub.subject_code,
    sub.subject_name,
    ss.class_time,
    ss.room_number,
    COUNT(DISTINCT e.student_id) as enrolled_students
FROM SECTION_SUBJECTS ss
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
LEFT JOIN ENROLLMENTS e ON sec.section_id = e.section_id AND e.status = 'active'
WHERE ss.teacher_id = ?
GROUP BY ss.section_subject_id
ORDER BY sec.section_name, sub.subject_code";

$my_classes_stmt = $conn->prepare($my_classes_sql);
$my_classes_stmt->bind_param("i", $teacher_id);
$my_classes_stmt->execute();
$my_classes_result = $my_classes_stmt->get_result();

$recent_sessions_sql = "SELECT 
    ats.session_id,
    ats.session_date,
    ats.start_time,
    ats.end_time,
    sec.section_name,
    sub.subject_code,
    COUNT(a.attendance_id) as marked_count
FROM ATTENDANCE_SESSIONS ats
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
LEFT JOIN ATTENDANCE a ON ats.session_id = a.session_id
WHERE ats.teacher_id = ?
GROUP BY ats.session_id
ORDER BY ats.session_date DESC, ats.start_time DESC
LIMIT 5";

$recent_sessions_stmt = $conn->prepare($recent_sessions_sql);
$recent_sessions_stmt->bind_param("i", $teacher_id);
$recent_sessions_stmt->execute();
$recent_sessions_result = $recent_sessions_stmt->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-home"></i> Teacher Dashboard</h1>
        <div class="breadcrumb">
            <span>Dashboard</span>
        </div>
    </div>
</div>

<div class="dashboard-cards">
    <div class="stat-card">
        <div class="stat-card-icon">
            <i class="fas fa-chalkboard"></i>
        </div>
        <div class="stat-card-title">Assigned Classes</div>
        <div class="stat-card-value"><?php echo $assigned_subjects_count; ?></div>
    </div>

    <div class="stat-card success">
        <div class="stat-card-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-card-title">Total Sessions</div>
        <div class="stat-card-value"><?php echo $total_sessions_count; ?></div>
    </div>

    <div class="stat-card warning">
        <div class="stat-card-icon">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-card-title">Today's Sessions</div>
        <div class="stat-card-value"><?php echo $today_sessions_count; ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
    </div>
    <div class="card-body">
        <div class="btn-group">
            <a href="create_session.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Create Session
            </a>
            <a href="my_classes.php" class="btn btn-success">
                <i class="fas fa-chalkboard"></i> My Classes
            </a>
            <a href="list_session.php" class="btn btn-info">
                <i class="fas fa-list"></i> View Sessions
            </a>
        </div>
    </div>
</div>

<div class="two-column-layout">
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-book"></i> My Classes</h2>
        </div>
        <div class="card-body">
            <?php if ($my_classes_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Subject</th>
                                <th>Students</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($class = $my_classes_result->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="badge badge-primary"><?php echo e($class['section_name']); ?></span></td>
                                    <td>
                                        <strong><?php echo e($class['subject_code']); ?></strong><br>
                                        <small><?php echo e($class['subject_name']); ?></small>
                                    </td>
                                    <td><?php echo $class['enrolled_students']; ?></td>
                                    <td><?php echo $class['class_time'] ? formatTime($class['class_time']) : 'N/A'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chalkboard"></i>
                    <h3>No Classes Assigned</h3>
                    <p>You have no classes assigned yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-history"></i> Recent Sessions</h2>
        </div>
        <div class="card-body">
            <?php if ($recent_sessions_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Section</th>
                                <th>Subject</th>
                                <th>Marked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($session = $recent_sessions_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo formatDate($session['session_date']); ?></td>
                                    <td><?php echo e($session['section_name']); ?></td>
                                    <td><?php echo e($session['subject_code']); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $session['marked_count']; ?> students</span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Sessions Yet</h3>
                    <p>No sessions have been created.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
$my_classes_stmt->close();
$recent_sessions_stmt->close();
include '../includes/footer.php';
?>