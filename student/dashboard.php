<?php
require_once '../config.php';
checkAuth('student');
$page_title = "Student Dashboard";

$student_id = $_SESSION['student_id'];

$total_sessions_sql = "SELECT COUNT(*) as count FROM ATTENDANCE WHERE student_id = ?";
$total_sessions_stmt = $conn->prepare($total_sessions_sql);
$total_sessions_stmt->bind_param("i", $student_id);
$total_sessions_stmt->execute();
$total_sessions = $total_sessions_stmt->get_result()->fetch_assoc()['count'];
$total_sessions_stmt->close();

$present_sql = "SELECT COUNT(*) as count FROM ATTENDANCE WHERE student_id = ? AND status = 'present'";
$present_stmt = $conn->prepare($present_sql);
$present_stmt->bind_param("i", $student_id);
$present_stmt->execute();
$present_count = $present_stmt->get_result()->fetch_assoc()['count'];
$present_stmt->close();

$absent_sql = "SELECT COUNT(*) as count FROM ATTENDANCE WHERE student_id = ? AND status = 'absent'";
$absent_stmt = $conn->prepare($absent_sql);
$absent_stmt->bind_param("i", $student_id);
$absent_stmt->execute();
$absent_count = $absent_stmt->get_result()->fetch_assoc()['count'];
$absent_stmt->close();

$attendance_percentage = $total_sessions > 0 ? round(($present_count / $total_sessions) * 100, 2) : 0;

$subject_wise_sql = "SELECT 
    sub.subject_code,
    sub.subject_name,
    COUNT(a.attendance_id) as total,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present
FROM ATTENDANCE a
JOIN ATTENDANCE_SESSIONS ats ON a.session_id = ats.session_id
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
WHERE a.student_id = ?
GROUP BY sub.subject_id";

$subject_wise_stmt = $conn->prepare($subject_wise_sql);
$subject_wise_stmt->bind_param("i", $student_id);
$subject_wise_stmt->execute();
$subject_wise_result = $subject_wise_stmt->get_result();

$recent_attendance_sql = "SELECT 
    ats.session_date,
    sub.subject_code,
    sub.subject_name,
    a.status,
    a.marked_at
FROM ATTENDANCE a
JOIN ATTENDANCE_SESSIONS ats ON a.session_id = ats.session_id
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
WHERE a.student_id = ?
ORDER BY ats.session_date DESC, a.marked_at DESC
LIMIT 5";

$recent_attendance_stmt = $conn->prepare($recent_attendance_sql);
$recent_attendance_stmt->bind_param("i", $student_id);
$recent_attendance_stmt->execute();
$recent_attendance_result = $recent_attendance_stmt->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-home"></i> Student Dashboard</h1>
        <div class="breadcrumb">
            <span>Dashboard</span>
        </div>
    </div>
</div>

<div class="dashboard-cards">
    <div class="stat-card">
        <div class="stat-card-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-card-title">Total Sessions</div>
        <div class="stat-card-value"><?php echo $total_sessions; ?></div>
    </div>

    <div class="stat-card success">
        <div class="stat-card-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-card-title">Present</div>
        <div class="stat-card-value"><?php echo $present_count; ?></div>
    </div>

    <div class="stat-card danger">
        <div class="stat-card-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-card-title">Absent</div>
        <div class="stat-card-value"><?php echo $absent_count; ?></div>
    </div>

    <div class="stat-card <?php echo $attendance_percentage >= 75 ? 'success' : 'warning'; ?>">
        <div class="stat-card-icon">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-card-title">Attendance</div>
        <div class="stat-card-value"><?php echo $attendance_percentage; ?>%</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
    </div>
    <div class="card-body">
        <div class="btn-group">
            <a href="mark_attendance.php" class="btn btn-primary">
                <i class="fas fa-qrcode"></i> Mark Attendance
            </a>
            <a href="my_attendance.php" class="btn btn-success">
                <i class="fas fa-list"></i> My Attendance
            </a>
            <a href="my_schedule.php" class="btn btn-info">
                <i class="fas fa-calendar"></i> My Schedule
            </a>
        </div>
    </div>
</div>

<div class="two-column-layout">
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-bar"></i> Subject-wise Attendance</h2>
        </div>
        <div class="card-body">
            <?php if ($subject_wise_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="mobile-cards">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Total</th>
                                <th>Present</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subject = $subject_wise_result->fetch_assoc()): 
                                $percent = calculateAttendancePercentage($subject['present'], $subject['total']);
                            ?>
                                <tr>
                                    <td data-label="Column Name">
                                        <strong><?php echo e($subject['subject_code']); ?></strong><br>
                                        <small><?php echo e($subject['subject_name']); ?></small>
                                    </td>
                                    <td data-label="Column Name"><?php echo $subject['total']; ?></td>
                                    <td data-label="Column Name"><?php echo $subject['present']; ?></td>
                                    <td data-label="Column Name"><?php echo getAttendancePercentageBadge($percent); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>No Attendance Data</h3>
                    <p>You have no attendance records yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-history"></i> Recent Attendance</h2>
        </div>
        <div class="card-body">
            <?php if ($recent_attendance_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="mobile-cards">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($attendance = $recent_attendance_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Session Date"><?php echo formatDate($attendance['session_date']); ?></td>
                                    <td data-label="Subject Code"><?php echo e($attendance['subject_code']); ?></td>
                                    <td data-label="Status"><?php echo getStatusBadge($attendance['status']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Records</h3>
                    <p>No attendance marked yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($attendance_percentage < 75): ?>
<div class="alert alert-warning" style="margin-top: 20px;">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Low Attendance Warning:</strong> Your attendance is below 75%. Please improve your attendance to meet minimum requirements.
</div>
<?php endif; ?>

<?php
$subject_wise_stmt->close();
$recent_attendance_stmt->close();
include '../includes/footer.php';
?>