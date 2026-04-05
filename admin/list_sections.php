<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Manage Sections";

$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$search = get('search', '');
$filter_year = get('filter_year', '');

$sql = "SELECT 
    s.section_id,
    s.section_name,
    s.semester,
    s.academic_year,
    s.total_students,
    s.is_active,
    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
    t.teacher_id,
    COUNT(DISTINCT e.enrollment_id) as enrolled_students,
    COUNT(DISTINCT ss.section_subject_id) as assigned_subjects
FROM SECTIONS s
LEFT JOIN TEACHERS t ON s.teacher_id = t.teacher_id
LEFT JOIN ENROLLMENTS e ON s.section_id = e.section_id AND e.status = 'active'
LEFT JOIN SECTION_SUBJECTS ss ON s.section_id = ss.section_id
WHERE 1=1";

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (s.section_name LIKE ? OR s.academic_year LIKE ?)";
}

if (!empty($filter_year)) {
    $sql .= " AND s.academic_year = ?";
}

$sql .= " GROUP BY s.section_id ORDER BY s.academic_year DESC, s.section_name ASC";

$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_year)) {
    $stmt->bind_param("sss", $search_term, $search_term, $filter_year);
} elseif (!empty($search)) {
    $stmt->bind_param("ss", $search_term, $search_term);
} elseif (!empty($filter_year)) {
    $stmt->bind_param("s", $filter_year);
}

$stmt->execute();
$result = $stmt->get_result();

$years_sql = "SELECT DISTINCT academic_year FROM SECTIONS ORDER BY academic_year DESC";
$years_result = $conn->query($years_sql);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-layer-group"></i> Manage Sections</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Sections</span>
        </div>
    </div>
    <div>
        <a href="add_section.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add New Section
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
                placeholder="Search by section name or year..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 200px;">
            <label for="filter_year"><i class="fas fa-filter"></i> Academic Year</label>
            <select id="filter_year" name="filter_year">
                <option value="">All Years</option>
                <?php while ($year = $years_result->fetch_assoc()): ?>
                    <option value="<?php echo e($year['academic_year']); ?>" <?php echo $filter_year == $year['academic_year'] ? 'selected' : ''; ?>>
                        <?php echo e($year['academic_year']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="list_sections.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Sections List 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> sections</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Section Name</th>
                            <th>Class Teacher</th>
                            <th>Semester</th>
                            <th>Academic Year</th>
                            <th>Enrolled Students</th>
                            <th>Assigned Subjects</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($section = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 16px; color: #2563eb;">
                                        <i class="fas fa-folder"></i> <?php echo e($section['section_name']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($section['teacher_name']): ?>
                                        <?php echo e($section['teacher_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-info">Semester <?php echo $section['semester']; ?></span></td>
                                <td><?php echo e($section['academic_year']); ?></td>
                                <td>
                                    <span class="badge badge-success">
                                        <?php echo $section['enrolled_students']; ?> students
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo $section['assigned_subjects']; ?> subjects
                                    </span>
                                </td>
                                <td>
                                    <?php if ($section['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit_section.php?id=<?php echo $section['section_id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Edit Section">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_section.php?id=<?php echo $section['section_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           title="Delete Section"
                                           onclick="return confirm('Are you sure you want to delete this section?');">
                                            <i class="fas fa-trash"></i>
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
                <i class="fas fa-layer-group"></i>
                <h3>No Sections Found</h3>
                <p>No sections match your search criteria or no sections have been created yet.</p>
                <a href="add_section.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Create First Section
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(total_students) as total_enrolled
FROM SECTIONS";
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
                <div style="color: #1e40af; font-weight: 500;">Total Sections</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $stats['active_count']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Active Sections</div>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>