<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Attendance Reports";

$report_type = get('report_type', 'student');
$student_id = get('student_id', '');
$section_id = get('section_id', '');
$subject_id = get('subject_id', '');
$date_from = get('date_from', date('Y-m-01'));
$date_to = get('date_to', date('Y-m-d'));

$students_sql = "SELECT student_id, student_card_id, first_name, last_name FROM STUDENTS ORDER BY first_name, last_name";
$students_result = $conn->query($students_sql);

$sections_sql = "SELECT section_id, section_name FROM SECTIONS ORDER BY section_name";
$sections_result = $conn->query($sections_sql);

$subjects_sql = "SELECT subject_id, subject_code, subject_name FROM SUBJECTS ORDER BY subject_code";
$subjects_result = $conn->query($subjects_sql);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-chart-bar"></i> Attendance Reports</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Reports</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-filter"></i> Report Filters</h2>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="form-group">
                <label for="report_type">Report Type</label>
                <select id="report_type" name="report_type" onchange="this.form.submit()">
                    <option value="student" <?php echo $report_type == 'student' ? 'selected' : ''; ?>>Student-wise Report</option>
                    <option value="overall" <?php echo $report_type == 'overall' ? 'selected' : ''; ?>>Overall Summary</option>
                </select>
            </div>
            
            <?php if ($report_type == 'student'): ?>
                <div class="form-group">
                    <label for="student_id">Select Student</label>
                    <select id="student_id" name="student_id">
                        <option value="">-- All Students --</option>
                        <?php while ($student = $students_result->fetch_assoc()): ?>
                            <option value="<?php echo $student['student_id']; ?>" <?php echo $student_id == $student['student_id'] ? 'selected' : ''; ?>>
                                <?php echo e($student['student_card_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php elseif ($report_type == 'section'): ?>
                <div class="form-group">
                    <label for="section_id">Select Section</label>
                    <select id="section_id" name="section_id">
                        <option value="">-- All Sections --</option>
                        <?php while ($section = $sections_result->fetch_assoc()): ?>
                            <option value="<?php echo $section['section_id']; ?>" <?php echo $section_id == $section['section_id'] ? 'selected' : ''; ?>>
                                <?php echo e($section['section_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php elseif ($report_type == 'subject'): ?>
                <div class="form-group">
                    <label for="subject_id">Select Subject</label>
                    <select id="subject_id" name="subject_id">
                        <option value="">-- All Subjects --</option>
                        <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                            <option value="<?php echo $subject['subject_id']; ?>" <?php echo $subject_id == $subject['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo e($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Generate Report
            </button>
        </form>
    </div>
</div>

<?php
if ($report_type == 'student' && !empty($student_id)):
    $report_sql = "SELECT 
        s.first_name, s.last_name, s.student_card_id,
        sub.subject_code, sub.subject_name,
        COUNT(a.attendance_id) as total_sessions,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM STUDENTS s
    JOIN ATTENDANCE a ON s.student_id = a.student_id
    JOIN ATTENDANCE_SESSIONS ats ON a.session_id = ats.session_id
    JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
    JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
    WHERE s.student_id = ? AND ats.session_date BETWEEN ? AND ?
    GROUP BY s.student_id, sub.subject_id";
    
    $report_stmt = $conn->prepare($report_sql);
    $report_stmt->bind_param("iss", $student_id, $date_from, $date_to);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-user"></i> Student Attendance Report</h2>
    </div>
    <div class="card-body">
        <?php if ($report_result->num_rows > 0): 
            $first_row = $report_result->fetch_assoc();
            $report_result->data_seek(0);
        ?>
            <h3><?php echo e($first_row['first_name'] . ' ' . $first_row['last_name']); ?> (<?php echo e($first_row['student_card_id']); ?>)</h3>
            <p class="text-muted">Period: <?php echo formatDate($date_from) . ' to ' . formatDate($date_to); ?></p>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Total Sessions</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $report_result->fetch_assoc()): 
                            $percentage = calculateAttendancePercentage($row['present_count'], $row['total_sessions']);
                        ?>
                            <tr>
                                <td><strong><?php echo e($row['subject_code']); ?></strong><br><small><?php echo e($row['subject_name']); ?></small></td>
                                <td><?php echo $row['total_sessions']; ?></td>
                                <td><span class="badge badge-success"><?php echo $row['present_count']; ?></span></td>
                                <td><span class="badge badge-danger"><?php echo $row['absent_count']; ?></span></td>
                                <td><span class="badge badge-warning"><?php echo $row['late_count']; ?></span></td>
                                <td><?php echo getAttendancePercentageBadge($percentage); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3>No Data Available</h3>
                <p>No attendance records found for the selected period.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
    $report_stmt->close();
elseif ($report_type == 'overall'):
    $overall_sql = "SELECT 
        COUNT(DISTINCT s.student_id) as total_students,
        COUNT(DISTINCT a.session_id) as total_sessions,
        COUNT(a.attendance_id) as total_records,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as total_absent
    FROM ATTENDANCE a
    JOIN STUDENTS s ON a.student_id = s.student_id
    JOIN ATTENDANCE_SESSIONS ats ON a.session_id = ats.session_id
    WHERE ats.session_date BETWEEN ? AND ?";
    
    $overall_stmt = $conn->prepare($overall_sql);
    $overall_stmt->bind_param("ss", $date_from, $date_to);
    $overall_stmt->execute();
    $overall_result = $overall_stmt->get_result();
    $overall = $overall_result->fetch_assoc();
    $overall_percentage = calculateAttendancePercentage($overall['total_present'], $overall['total_records']);
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Overall Attendance Summary</h2>
    </div>
    <div class="card-body">
        <p class="text-muted">Period: <?php echo formatDate($date_from) . ' to ' . formatDate($date_to); ?></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
            <div style="text-align: center; padding: 20px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 36px; font-weight: bold; color: #2563eb;"><?php echo $overall['total_students']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Total Students</div>
            </div>
            <div style="text-align: center; padding: 20px; background-color: #fef3c7; border-radius: 8px;">
                <div style="font-size: 36px; font-weight: bold; color: #f59e0b;"><?php echo $overall['total_sessions']; ?></div>
                <div style="color: #92400e; font-weight: 500;">Total Sessions</div>
            </div>
            <div style="text-align: center; padding: 20px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 36px; font-weight: bold; color: #10b981;"><?php echo $overall['total_present']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Total Present</div>
            </div>
            <div style="text-align: center; padding: 20px; background-color: #fee2e2; border-radius: 8px;">
                <div style="font-size: 36px; font-weight: bold; color: #ef4444;"><?php echo $overall['total_absent']; ?></div>
                <div style="color: #991b1b; font-weight: 500;">Total Absent</div>
            </div>
            <div style="text-align: center; padding: 20px; background-color: <?php echo $overall_percentage >= 75 ? '#d1fae5' : '#fee2e2'; ?>; border-radius: 8px;">
                <div style="font-size: 36px; font-weight: bold; color: <?php echo $overall_percentage >= 75 ? '#10b981' : '#ef4444'; ?>;"><?php echo $overall_percentage; ?>%</div>
                <div style="color: <?php echo $overall_percentage >= 75 ? '#065f46' : '#991b1b'; ?>; font-weight: 500;">Overall Attendance</div>
            </div>
        </div>
    </div>
</div>

<?php
    $overall_stmt->close();
endif;
?>

<?php
include '../includes/footer.php';
?>