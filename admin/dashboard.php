<?php
/**
 * Admin Dashboard
 * Shows overview statistics and quick links
 */

// Include configuration and check authentication
require_once '../config.php';
checkAuth('admin');

// Set page title
$page_title = "Admin Dashboard";

// Get statistics
// Total Students
$students_query = "SELECT COUNT(*) as total FROM STUDENTS WHERE status = 'active'";
$students_result = $conn->query($students_query);
$total_students = $students_result->fetch_assoc()['total'];

// Total Teachers
$teachers_query = "SELECT COUNT(*) as total FROM TEACHERS";
$teachers_result = $conn->query($teachers_query);
$total_teachers = $teachers_result->fetch_assoc()['total'];

// Total Sections
$sections_query = "SELECT COUNT(*) as total FROM SECTIONS WHERE is_active = 1";
$sections_result = $conn->query($sections_query);
$total_sections = $sections_result->fetch_assoc()['total'];

// Total Subjects
$subjects_query = "SELECT COUNT(*) as total FROM SUBJECTS";
$subjects_result = $conn->query($subjects_query);
$total_subjects = $subjects_result->fetch_assoc()['total'];

// Total Attendance Sessions (Today)
$sessions_today_query = "SELECT COUNT(*) as total FROM ATTENDANCE_SESSIONS WHERE session_date = CURDATE()";
$sessions_today_result = $conn->query($sessions_today_query);
$total_sessions_today = $sessions_today_result->fetch_assoc()['total'];

// Total Enrollments
$enrollments_query = "SELECT COUNT(*) as total FROM ENROLLMENTS WHERE status = 'active'";
$enrollments_result = $conn->query($enrollments_query);
$total_enrollments = $enrollments_result->fetch_assoc()['total'];

// Get recent attendance sessions (last 5)
$recent_sessions_query = "SELECT 
    ats.session_id,
    ats.session_date,
    ats.start_time,
    ats.end_time,
    ats.session_type,
    sec.section_name,
    sub.subject_code,
    sub.subject_name,
    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
    COUNT(a.attendance_id) as marked_attendance
FROM ATTENDANCE_SESSIONS ats
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
JOIN TEACHERS t ON ats.teacher_id = t.teacher_id
LEFT JOIN ATTENDANCE a ON ats.session_id = a.session_id
GROUP BY ats.session_id
ORDER BY ats.session_date DESC, ats.start_time DESC
LIMIT 5";
$recent_sessions_result = $conn->query($recent_sessions_query);

// Get low attendance students (below 75%)
$low_attendance_query = "SELECT 
    s.student_id,
    s.student_card_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    COUNT(a.attendance_id) as total_sessions,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.attendance_id)) * 100, 2) as attendance_percentage
FROM STUDENTS s
JOIN ATTENDANCE a ON s.student_id = a.student_id
GROUP BY s.student_id
HAVING attendance_percentage < 75
ORDER BY attendance_percentage ASC
LIMIT 5";
$low_attendance_result = $conn->query($low_attendance_query);

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-home"></i> Admin Dashboard</h1>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>/</span>
            <span>Dashboard</span>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="stat-card">
        <div class="stat-card-icon">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-card-title">Total Students</div>
        <div class="stat-card-value"><?php echo $total_students; ?></div>
    </div>

    <div class="stat-card success">
        <div class="stat-card-icon">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-card-title">Total Teachers</div>
        <div class="stat-card-value"><?php echo $total_teachers; ?></div>
    </div>

    <div class="stat-card warning">
        <div class="stat-card-icon">
            <i class="fas fa-layer-group"></i>
        </div>
        <div class="stat-card-title">Active Sections</div>
        <div class="stat-card-value"><?php echo $total_sections; ?></div>
    </div>

    <div class="stat-card danger">
        <div class="stat-card-icon">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-card-title">Total Subjects</div>
        <div class="stat-card-value"><?php echo $total_subjects; ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
    </div>
    <div class="card-body">
        <div class="btn-group">
            <a href="add_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add New Student
            </a>
            <a href="add_user.php" class="btn btn-success">
                <i class="fas fa-user-tie"></i> Add New Teacher
            </a>
            <a href="add_section.php" class="btn btn-warning">
                <i class="fas fa-plus-circle"></i> Create Section
            </a>
            <a href="add_subject.php" class="btn btn-info">
                <i class="fas fa-book-medical"></i> Add Subject
            </a>
            <a href="assign_subject.php" class="btn btn-info">
                <i class="fas fa-book-medical"></i> Assign Subject
            </a>
            <a href="add_enrollment.php" class="btn btn-secondary">
                <i class="fas fa-user-check"></i> Enroll Student
            </a>

        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="two-column-layout">
    
    <!-- Recent Attendance Sessions -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-calendar-check"></i> Recent Attendance Sessions</h2>
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
                                <th>Teacher</th>
                                <th>Marked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($session = $recent_sessions_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo formatDate($session['session_date']); ?></td>
                                    <td><?php echo e($session['section_name']); ?></td>
                                    <td><?php echo e($session['subject_code']); ?></td>
                                    <td><?php echo e($session['teacher_name']); ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $session['marked_attendance']; ?> students
                                        </span>
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
                    <p>No attendance sessions have been created.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Attendance Alerts -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-exclamation-triangle"></i> Low Attendance Alerts</h2>
        </div>
        <div class="card-body">
            <?php if ($low_attendance_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Card ID</th>
                                <th>Student Name</th>
                                <th>Sessions</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $low_attendance_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($student['student_card_id']); ?></td>
                                    <td><?php echo e($student['student_name']); ?></td>
                                    <td><?php echo $student['total_sessions']; ?></td>
                                    <td>
                                        <?php echo getAttendancePercentageBadge($student['attendance_percentage']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <a href="notifications.php" class="btn btn-sm btn-warning">
                        <i class="fas fa-bell"></i> View All Notifications
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>All Good!</h3>
                    <p>No students with low attendance.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Additional Statistics -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> System Information</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <strong><i class="fas fa-calendar-day"></i> Sessions Today:</strong>
                <span class="badge badge-primary"><?php echo $total_sessions_today; ?></span>
            </div>
            <div>
                <strong><i class="fas fa-user-check"></i> Total Enrollments:</strong>
                <span class="badge badge-success"><?php echo $total_enrollments; ?></span>
            </div>
            <div>
                <strong><i class="fas fa-calendar-alt"></i> Academic Year:</strong>
                <span class="badge badge-info"><?php echo getCurrentAcademicYear(); ?></span>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>