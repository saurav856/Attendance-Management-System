<?php
/**
 * Student - My Attendance
 * Display attendance records and statistics for the logged-in student
 */

require_once '../config.php';
checkAuth('student');
$page_title = "My Attendance";

$student_id = $_SESSION['student_id'];

// Get filter parameters
$filter_subject = get('filter_subject', '');
$filter_status = get('filter_status', '');
$date_from = get('date_from', date('Y-m-01')); // First day of current month
$date_to = get('date_to', date('Y-m-d')); // Today

// Build query to get attendance records
$sql = "SELECT 
    a.attendance_id,
    a.status,
    a.marked_at,
    ats.session_date,
    ats.start_time,
    ats.end_time,
    ats.session_type,
    sub.subject_id,
    sub.subject_code,
    sub.subject_name,
    sec.section_name
FROM ATTENDANCE a
JOIN ATTENDANCE_SESSIONS ats ON a.session_id = ats.session_id
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
WHERE a.student_id = ?";

// Apply filters
if (!empty($filter_subject)) {
    $sql .= " AND sub.subject_id = ?";
}

if (!empty($filter_status)) {
    $sql .= " AND a.status = ?";
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND ats.session_date BETWEEN ? AND ?";
}

$sql .= " ORDER BY ats.session_date DESC, ats.start_time DESC";

// Prepare and execute statement
$stmt = $conn->prepare($sql);

if (!empty($filter_subject) && !empty($filter_status)) {
    $stmt->bind_param("iiss", $student_id, $filter_subject, $filter_status, $date_from, $date_to);
} elseif (!empty($filter_subject)) {
    $stmt->bind_param("iiss", $student_id, $filter_subject, $date_from, $date_to);
} elseif (!empty($filter_status)) {
    $stmt->bind_param("isss", $student_id, $filter_status, $date_from, $date_to);
} else {
    $stmt->bind_param("iss", $student_id, $date_from, $date_to);
}

$stmt->execute();
$result = $stmt->get_result();

// Get subjects for filter dropdown
$subjects_sql = "SELECT DISTINCT sub.subject_id, sub.subject_code, sub.subject_name
                FROM ATTENDANCE a
                JOIN ATTENDANCE_SESSIONS ats ON a.session_id = ats.session_id
                JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
                JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
                WHERE a.student_id = ?
                ORDER BY sub.subject_code";
$subjects_stmt = $conn->prepare($subjects_sql);
$subjects_stmt->bind_param("i", $student_id);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();

// Get subject-wise statistics
$stats_sql = "SELECT 
    sub.subject_id,
    sub.subject_code,
    sub.subject_name,
    COUNT(a.attendance_id) as total_sessions,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
FROM ATTENDANCE a
JOIN ATTENDANCE_SESSIONS ats ON a.session_id = ats.session_id
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
WHERE a.student_id = ?
GROUP BY sub.subject_id";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $student_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-clipboard-list"></i> My Attendance Records</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>My Attendance</span>
        </div>
    </div>
</div>

<!-- Subject-wise Statistics -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-bar"></i> Subject-wise Attendance Summary</h2>
    </div>
    <div class="card-body">
        <?php if ($stats_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="mobile-cards">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Total Sessions</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($stat = $stats_result->fetch_assoc()): 
                            $percentage = calculateAttendancePercentage($stat['present_count'], $stat['total_sessions']);
                        ?>
                            <tr>
                                <td><strong><?php echo e($stat['subject_code']); ?></strong></td>
                                <td><?php echo e($stat['subject_name']); ?></td>
                                <td><span class="badge badge-info"><?php echo $stat['total_sessions']; ?></span></td>
                                <td><span class="badge badge-success"><?php echo $stat['present_count']; ?></span></td>
                                <td><span class="badge badge-danger"><?php echo $stat['absent_count']; ?></span></td>
                                <td><span class="badge badge-warning"><?php echo $stat['late_count']; ?></span></td>
                                <td><?php echo getAttendancePercentageBadge($percentage); ?></td>
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

<!-- Search and Filter Section -->
<div class="search-filter-section" style="margin-top: 20px;">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 200px;">
            <label for="filter_subject"><i class="fas fa-filter"></i> Subject</label>
            <select id="filter_subject" name="filter_subject">
                <option value="">All Subjects</option>
                <?php 
                $subjects_result->data_seek(0); // Reset pointer
                while ($subject = $subjects_result->fetch_assoc()): 
                ?>
                    <option value="<?php echo $subject['subject_id']; ?>" <?php echo $filter_subject == $subject['subject_id'] ? 'selected' : ''; ?>>
                        <?php echo e($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
            <label for="filter_status"><i class="fas fa-filter"></i> Status</label>
            <select id="filter_status" name="filter_status">
                <option value="">All Status</option>
                <option value="present" <?php echo $filter_status == 'present' ? 'selected' : ''; ?>>Present</option>
                <option value="absent" <?php echo $filter_status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                <option value="late" <?php echo $filter_status == 'late' ? 'selected' : ''; ?>>Late</option>
            </select>
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
            <label for="date_from"><i class="fas fa-calendar"></i> From</label>
            <input 
                type="date" 
                id="date_from" 
                name="date_from" 
                value="<?php echo htmlspecialchars($date_from); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
            <label for="date_to"><i class="fas fa-calendar"></i> To</label>
            <input 
                type="date" 
                id="date_to" 
                name="date_to" 
                value="<?php echo htmlspecialchars($date_to); ?>"
            >
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="my_attendance.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Attendance Records List -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Attendance Records 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> records</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="mobile-cards">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Marked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($record = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatDate($record['session_date']); ?></td>
                                <td><span class="badge badge-primary"><?php echo e($record['section_name']); ?></span></td>
                                <td>
                                    <strong><?php echo e($record['subject_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($record['subject_name']); ?></small>
                                </td>
                                <td><?php echo formatTime($record['start_time']) . ' - ' . formatTime($record['end_time']); ?></td>
                                <td><span class="badge badge-info"><?php echo ucfirst($record['session_type']); ?></span></td>
                                <td><?php echo getStatusBadge($record['status']); ?></td>
                                <td><?php echo formatDateTime($record['marked_at']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Records Found</h3>
                <p>No attendance records match your filter criteria.</p>
                <a href="my_attendance.php" class="btn btn-secondary">
                    Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Information Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> Attendance Status Legend</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <span class="badge badge-success">Present</span> - You attended the class
            </div>
            <div>
                <span class="badge badge-danger">Absent</span> - You were absent from the class
            </div>
            <div>
                <span class="badge badge-warning">Late</span> - You arrived late to the class
            </div>
        </div>
        <div style="margin-top: 20px;">
            <p><strong><i class="fas fa-exclamation-triangle"></i> Note:</strong> Maintain at least 75% attendance in each subject to meet minimum requirements.</p>
        </div>
    </div>
</div>

<?php
$stmt->close();
$subjects_stmt->close();
$stats_stmt->close();
include '../includes/footer.php';
?>