<?php
require_once '../config.php';
checkAuth('teacher');
$page_title = "View Attendance";

$teacher_id = $_SESSION['teacher_id'];
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id == 0) {
    header("Location: list_session.php");
    exit();
}

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

$attendance_sql = "SELECT 
    s.student_id,
    s.student_card_id,
    s.first_name,
    s.last_name,
    s.email,
    a.status,
    a.marked_at,
    a.remarks
FROM ENROLLMENTS e
JOIN STUDENTS s ON e.student_id = s.student_id
JOIN SECTION_SUBJECTS ss ON e.section_id = ss.section_id
LEFT JOIN ATTENDANCE a ON s.student_id = a.student_id AND a.session_id = ?
WHERE ss.section_subject_id = ? AND e.status = 'active'
ORDER BY a.status IS NULL DESC, s.last_name, s.first_name";

$attendance_stmt = $conn->prepare($attendance_sql);
$attendance_stmt->bind_param("ii", $session_id, $session['section_subject_id']);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();

$stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0,
    'on_leave' => 0,
    'not_marked' => 0
];

$temp_results = [];
while ($row = $attendance_result->fetch_assoc()) {
    $temp_results[] = $row;
    $stats['total']++;
    if ($row['status']) {
        $stats[$row['status']]++;
    } else {
        $stats['not_marked']++;
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-eye"></i> View Attendance</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="list_session.php">Sessions</a>
            <span>/</span>
            <span>View Attendance</span>
        </div>
    </div>
    <div>
        <a href="list_session.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Sessions
        </a>
        <a href="mark_attendance.php?session_id=<?php echo $session_id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Attendance
        </a>
    </div>
</div>

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
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
    <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold; color: #10b981;"><?php echo $stats['present']; ?></div>
        <div style="color: #065f46; font-weight: 500;">Present</div>
    </div>
    <div style="text-align: center; padding: 15px; background-color: #fee2e2; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold; color: #ef4444;"><?php echo $stats['absent']; ?></div>
        <div style="color: #991b1b; font-weight: 500;">Absent</div>
    </div>
    <div style="text-align: center; padding: 15px; background-color: #fef3c7; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold; color: #f59e0b;"><?php echo $stats['late']; ?></div>
        <div style="color: #92400e; font-weight: 500;">Late</div>
    </div>
    <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold; color: #3b82f6;"><?php echo $stats['excused']; ?></div>
        <div style="color: #1e40af; font-weight: 500;">Excused</div>
    </div>
    <div style="text-align: center; padding: 15px; background-color: #f3f4f6; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold; color: #6b7280;"><?php echo $stats['on_leave']; ?></div>
        <div style="color: #374151; font-weight: 500;">On Leave</div>
    </div>
    <div style="text-align: center; padding: 15px; background-color: #e5e7eb; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold; color: #6b7280;"><?php echo $stats['not_marked']; ?></div>
        <div style="color: #374151; font-weight: 500;">Not Marked</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-users"></i> Attendance Details
            <span class="badge badge-primary"><?php echo $stats['total']; ?> students</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if (count($temp_results) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Card ID</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Marked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($temp_results as $record): ?>
                            <tr>
                                <td><strong><?php echo e($record['student_card_id']); ?></strong></td>
                                <td><?php echo e($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                <td><?php echo $record['email'] ? e($record['email']) : '<span class="text-muted">N/A</span>'; ?></td>
                                <td>
                                    <?php if ($record['status']): ?>
                                        <?php echo getStatusBadge($record['status']); ?>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Not Marked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['marked_at']): ?>
                                        <?php echo formatDateTime($record['marked_at']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Students</h3>
                <p>No students are enrolled in this section.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$attendance_stmt->close();
include '../includes/footer.php';
?>