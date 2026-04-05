<?php
require_once '../config.php';
checkAuth('teacher');
$page_title = "My Sessions";

$teacher_id = $_SESSION['teacher_id'];
$search = get('search', '');
$filter_date = get('filter_date', '');

$sql = "SELECT 
    ats.session_id,
    ats.session_date,
    ats.start_time,
    ats.end_time,
    ats.session_type,
    ats.is_active,
    sec.section_name,
    sub.subject_code,
    sub.subject_name,
    COUNT(a.attendance_id) as marked_count,
    COUNT(DISTINCT e.student_id) as total_students
FROM ATTENDANCE_SESSIONS ats
JOIN SECTION_SUBJECTS ss ON ats.section_subject_id = ss.section_subject_id
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
LEFT JOIN ENROLLMENTS e ON sec.section_id = e.section_id AND e.status = 'active'
LEFT JOIN ATTENDANCE a ON ats.session_id = a.session_id
WHERE ats.teacher_id = ?";

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (sec.section_name LIKE ? OR sub.subject_code LIKE ? OR sub.subject_name LIKE ?)";
}

if (!empty($filter_date)) {
    $sql .= " AND ats.session_date = ?";
}

$sql .= " GROUP BY ats.session_id ORDER BY ats.session_date DESC, ats.start_time DESC";

$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_date)) {
    $stmt->bind_param("issss", $teacher_id, $search_term, $search_term, $search_term, $filter_date);
} elseif (!empty($search)) {
    $stmt->bind_param("isss", $teacher_id, $search_term, $search_term, $search_term);
} elseif (!empty($filter_date)) {
    $stmt->bind_param("is", $teacher_id, $filter_date);
} else {
    $stmt->bind_param("i", $teacher_id);
}

$stmt->execute();
$result = $stmt->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-list"></i> My Sessions</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Sessions</span>
        </div>
    </div>
    <div>
        <a href="create_session.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Create New Session
        </a>
    </div>
</div>

<div class="search-filter-section">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 2; margin-bottom: 0; min-width: 250px;">
            <label for="search"><i class="fas fa-search"></i> Search</label>
            <input 
                type="text" 
                id="search" 
                name="search" 
                placeholder="Search by section or subject..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 200px;">
            <label for="filter_date"><i class="fas fa-calendar"></i> Date</label>
            <input 
                type="date" 
                id="filter_date" 
                name="filter_date" 
                value="<?php echo htmlspecialchars($filter_date); ?>"
            >
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="list_session.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-calendar-alt"></i> Sessions List 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> sessions</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($session = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatDate($session['session_date']); ?></td>
                                <td><span class="badge badge-primary"><?php echo e($session['section_name']); ?></span></td>
                                <td>
                                    <strong><?php echo e($session['subject_code']); ?></strong><br>
                                    <small><?php echo e($session['subject_name']); ?></small>
                                </td>
                                <td><?php echo formatTime($session['start_time']) . ' - ' . formatTime($session['end_time']); ?></td>
                                <td><span class="badge badge-info"><?php echo ucfirst($session['session_type']); ?></span></td>
                                <td>
                                    <strong><?php echo $session['marked_count']; ?></strong> / <?php echo $session['total_students']; ?>
                                    <?php if ($session['total_students'] > 0): ?>
                                        <br><small><?php echo round(($session['marked_count'] / $session['total_students']) * 100); ?>% marked</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="mark_attendance.php?session_id=<?php echo $session['session_id']; ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="Mark Attendance">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_attendance.php?session_id=<?php echo $session['session_id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="View Attendance">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Sessions Found</h3>
                <p>You haven't created any sessions yet or no sessions match your search.</p>
                <a href="create_session.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Create First Session
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>