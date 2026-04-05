<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Manage Enrollments";

$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$search = get('search', '');
$filter_section = get('filter_section', '');
$filter_status = get('filter_status', '');

$sql = "SELECT 
    e.enrollment_id,
    e.enrollment_date,
    e.status,
    s.student_id,
    s.student_card_id,
    s.first_name,
    s.last_name,
    sec.section_id,
    sec.section_name,
    sec.academic_year,
    sec.semester
FROM ENROLLMENTS e
JOIN STUDENTS s ON e.student_id = s.student_id
JOIN SECTIONS sec ON e.section_id = sec.section_id
WHERE 1=1";

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_card_id LIKE ? OR sec.section_name LIKE ?)";
}

if (!empty($filter_section)) {
    $sql .= " AND e.section_id = ?";
}

if (!empty($filter_status)) {
    $sql .= " AND e.status = ?";
}

$sql .= " ORDER BY e.enrollment_date DESC, s.last_name ASC";

$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_section) && !empty($filter_status)) {
    $stmt->bind_param("ssssis", $search_term, $search_term, $search_term, $search_term, $filter_section, $filter_status);
} elseif (!empty($search) && !empty($filter_section)) {
    $stmt->bind_param("ssssi", $search_term, $search_term, $search_term, $search_term, $filter_section);
} elseif (!empty($search) && !empty($filter_status)) {
    $stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $filter_status);
} elseif (!empty($search)) {
    $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
} elseif (!empty($filter_section) && !empty($filter_status)) {
    $stmt->bind_param("is", $filter_section, $filter_status);
} elseif (!empty($filter_section)) {
    $stmt->bind_param("i", $filter_section);
} elseif (!empty($filter_status)) {
    $stmt->bind_param("s", $filter_status);
}

$stmt->execute();
$result = $stmt->get_result();

$sections_sql = "SELECT section_id, section_name FROM SECTIONS WHERE is_active = 1 ORDER BY section_name";
$sections_result = $conn->query($sections_sql);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-user-check"></i> Manage Enrollments</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Enrollments</span>
        </div>
    </div>
    <div>
        <a href="add_enrollment.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Enroll Student
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<div class="search-filter-section">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 2; margin-bottom: 0; min-width: 250px;">
            <label for="search"><i class="fas fa-search"></i> Search</label>
            <input 
                type="text" 
                id="search" 
                name="search" 
                placeholder="Search by student name, card ID, or section..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
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
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
            <label for="filter_status"><i class="fas fa-filter"></i> Status</label>
            <select id="filter_status" name="filter_status">
                <option value="">All Statuses</option>
                <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="dropped" <?php echo $filter_status == 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="list_enrollments.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Enrollments List 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> enrollments</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Card ID</th>
                            <th>Student Name</th>
                            <th>Section</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Enrolled Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($enrollment = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($enrollment['student_card_id']); ?></strong></td>
                                <td><?php echo e($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></td>
                                <td><span class="badge badge-primary"><?php echo e($enrollment['section_name']); ?></span></td>
                                <td><?php echo e($enrollment['academic_year']); ?></td>
                                <td><span class="badge badge-info">Semester <?php echo $enrollment['semester']; ?></span></td>
                                <td><?php echo formatDate($enrollment['enrollment_date']); ?></td>
                                <td><?php echo getStatusBadge($enrollment['status']); ?></td>
                                <td>
                                    <a href="delete_enrollment.php?id=<?php echo $enrollment['enrollment_id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="Remove Enrollment"
                                       onclick="return confirm('Remove this enrollment?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-check"></i>
                <h3>No Enrollments Found</h3>
                <p>No enrollments match your criteria or no students have been enrolled yet.</p>
                <a href="add_enrollment.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Enroll First Student
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
FROM ENROLLMENTS";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Quick Statistics</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #2563eb;"><?php echo $stats['total']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Total Enrollments</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $stats['active_count']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Active Enrollments</div>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>