<?php
/**
 * Teacher - My Classes
 * Display all classes (section-subject assignments) assigned to the logged-in teacher
 */

require_once '../config.php';
checkAuth('teacher');
$page_title = "My Classes";

$teacher_id = $_SESSION['teacher_id'];

// Get search parameters
$search = get('search', '');
$filter_section = get('filter_section', '');

// Build query to get all assigned classes
$sql = "SELECT 
    ss.section_subject_id,
    ss.academic_year,
    ss.semester,
    ss.class_time,
    ss.room_number,
    sec.section_id,
    sec.section_name,
    sub.subject_id,
    sub.subject_code,
    sub.subject_name,
    sub.credit_hours,
    COUNT(DISTINCT e.student_id) as enrolled_students,
    COUNT(DISTINCT ats.session_id) as total_sessions
FROM SECTION_SUBJECTS ss
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
LEFT JOIN ENROLLMENTS e ON sec.section_id = e.section_id AND e.status = 'active'
LEFT JOIN ATTENDANCE_SESSIONS ats ON ss.section_subject_id = ats.section_subject_id
WHERE ss.teacher_id = ?";

// Apply search filter
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (sec.section_name LIKE ? OR sub.subject_code LIKE ? OR sub.subject_name LIKE ?)";
}

// Apply section filter
if (!empty($filter_section)) {
    $sql .= " AND ss.section_id = ?";
}

$sql .= " GROUP BY ss.section_subject_id ORDER BY sec.section_name ASC, sub.subject_code ASC";

// Prepare and execute statement
$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_section)) {
    $stmt->bind_param("isssi", $teacher_id, $search_term, $search_term, $search_term, $filter_section);
} elseif (!empty($search)) {
    $stmt->bind_param("isss", $teacher_id, $search_term, $search_term, $search_term);
} elseif (!empty($filter_section)) {
    $stmt->bind_param("ii", $teacher_id, $filter_section);
} else {
    $stmt->bind_param("i", $teacher_id);
}

$stmt->execute();
$result = $stmt->get_result();

// Get all sections for filter dropdown
$sections_sql = "SELECT DISTINCT sec.section_id, sec.section_name 
                FROM SECTION_SUBJECTS ss
                JOIN SECTIONS sec ON ss.section_id = sec.section_id
                WHERE ss.teacher_id = ?
                ORDER BY sec.section_name";
$sections_stmt = $conn->prepare($sections_sql);
$sections_stmt->bind_param("i", $teacher_id);
$sections_stmt->execute();
$sections_result = $sections_stmt->get_result();

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-chalkboard"></i> My Classes</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>My Classes</span>
        </div>
    </div>
    <div>
        <a href="create_session.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Create Session
        </a>
    </div>
</div>

<!-- Search and Filter Section -->
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
            <label for="filter_section"><i class="fas fa-filter"></i> Section</label>
            <select id="filter_section" name="filter_section">
                <option value="">All Sections</option>
                <?php while ($section = $sections_result->fetch_assoc()): ?>
                    <option value="<?php echo $section['section_id']; ?>" <?php echo $filter_section == $section['section_id'] ? 'selected' : ''; ?>>
                        <?php echo e($section['section_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="my_classes.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Classes List Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> My Assigned Classes 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> classes</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Credit Hours</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Students</th>
                            <th>Sessions</th>
                            <th>Class Time</th>
                            <th>Room</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($class = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-primary" style="font-size: 14px;">
                                        <?php echo e($class['section_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo e($class['subject_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($class['subject_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $class['credit_hours']; ?> hrs</span>
                                </td>
                                <td><?php echo e($class['academic_year']); ?></td>
                                <td>
                                    <span class="badge badge-secondary">Semester <?php echo $class['semester']; ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-success">
                                        <i class="fas fa-users"></i> <?php echo $class['enrolled_students']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-calendar-check"></i> <?php echo $class['total_sessions']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($class['class_time']): ?>
                                        <i class="fas fa-clock"></i> <?php echo formatTime($class['class_time']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($class['room_number']): ?>
                                        <i class="fas fa-door-open"></i> <?php echo e($class['room_number']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="create_session.php?section_subject_id=<?php echo $class['section_subject_id']; ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="Create Session">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <a href="list_session.php?section_subject_id=<?php echo $class['section_subject_id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="View Sessions">
                                            <i class="fas fa-list"></i>
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
                <i class="fas fa-chalkboard"></i>
                <h3>No Classes Assigned</h3>
                <p>You have no classes assigned yet or no classes match your search criteria.</p>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Card -->
<?php
// Get overall statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT ss.section_subject_id) as total_classes,
    COUNT(DISTINCT sec.section_id) as total_sections,
    COUNT(DISTINCT sub.subject_id) as total_subjects,
    COUNT(DISTINCT e.student_id) as total_students
FROM SECTION_SUBJECTS ss
JOIN SECTIONS sec ON ss.section_id = sec.section_id
JOIN SUBJECTS sub ON ss.subject_id = sub.subject_id
LEFT JOIN ENROLLMENTS e ON sec.section_id = e.section_id AND e.status = 'active'
WHERE ss.teacher_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $teacher_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Teaching Statistics</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #2563eb;"><?php echo $stats['total_classes']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Total Classes</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #fef3c7; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo $stats['total_sections']; ?></div>
                <div style="color: #92400e; font-weight: 500;">Sections Teaching</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $stats['total_subjects']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Different Subjects</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #fee2e2; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #ef4444;"><?php echo $stats['total_students']; ?></div>
                <div style="color: #991b1b; font-weight: 500;">Total Students</div>
            </div>
        </div>
    </div>
</div>

<!-- Information Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> Information</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li><strong>Classes:</strong> These are the section-subject combinations you are assigned to teach</li>
            <li><strong>Students:</strong> Number of students enrolled in each section</li>
            <li><strong>Sessions:</strong> Total attendance sessions you have created for each class</li>
            <li>Click <i class="fas fa-plus"></i> to create a new attendance session for a class</li>
            <li>Click <i class="fas fa-list"></i> to view all sessions and attendance records for a class</li>
            <li>You can create attendance sessions from this page or from the <a href="session_create.php">Create Session</a> page</li>
        </ul>
    </div>
</div>

<?php
$stmt->close();
$sections_stmt->close();
include '../includes/footer.php';
?>