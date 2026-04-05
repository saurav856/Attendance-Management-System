<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Manage Teachers";

$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$search = get('search', '');
$filter_department = get('filter_department', '');

$sql = "SELECT 
    t.teacher_id,
    t.user_id,
    t.first_name,
    t.last_name,
    t.email,
    t.phone,
    t.department,
    t.joining_date,
    u.username,
    u.is_active,
    COUNT(DISTINCT ss.section_subject_id) as assigned_subjects,
    COUNT(DISTINCT ats.session_id) as total_sessions
FROM TEACHERS t
JOIN USERS u ON t.user_id = u.user_id
LEFT JOIN SECTION_SUBJECTS ss ON t.teacher_id = ss.teacher_id
LEFT JOIN ATTENDANCE_SESSIONS ats ON t.teacher_id = ats.teacher_id
WHERE 1=1";

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.email LIKE ? OR t.department LIKE ? OR u.username LIKE ?)";
}

if (!empty($filter_department)) {
    $sql .= " AND t.department = ?";
}

$sql .= " GROUP BY t.teacher_id ORDER BY t.joining_date DESC, t.last_name ASC";

$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_department)) {
    $stmt->bind_param("ssssss", $search_term, $search_term, $search_term, $search_term, $search_term, $filter_department);
} elseif (!empty($search)) {
    $stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $search_term);
} elseif (!empty($filter_department)) {
    $stmt->bind_param("s", $filter_department);
}

$stmt->execute();
$result = $stmt->get_result();

$departments_sql = "SELECT DISTINCT department FROM TEACHERS WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = $conn->query($departments_sql);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Teachers</span>
        </div>
    </div>
    <div>
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New Teacher
        </a>
    </div>
</div>

<?php if ($error): ?>
    <?php echo showError($error); ?>
<?php endif; ?>

<?php if ($success): ?>
    <?php echo showSuccess($success); ?>
<?php endif; ?>

<div class="search-filter-section">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 2; margin-bottom: 0; min-width: 250px;">
            <label for="search"><i class="fas fa-search"></i> Search</label>
            <input 
                type="text" 
                id="search" 
                name="search" 
                placeholder="Search by name, email, department, or username..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 200px;">
            <label for="filter_department"><i class="fas fa-filter"></i> Department</label>
            <select id="filter_department" name="filter_department">
                <option value="">All Departments</option>
                <?php while ($dept = $departments_result->fetch_assoc()): ?>
                    <option value="<?php echo e($dept['department']); ?>" <?php echo $filter_department == $dept['department'] ? 'selected' : ''; ?>>
                        <?php echo e($dept['department']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="list_teacher.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Teachers List 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> teachers</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Teacher Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Username</th>
                            <th>Assigned Subjects</th>
                            <th>Sessions</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($teacher = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $teacher['teacher_id']; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-tie" style="color: #f59e0b; font-size: 20px;"></i>
                                        <strong><?php echo e($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo $teacher['email'] ? e($teacher['email']) : '<span class="text-muted">N/A</span>'; ?></td>
                                <td><?php echo $teacher['phone'] ? e($teacher['phone']) : '<span class="text-muted">N/A</span>'; ?></td>
                                <td>
                                    <?php if ($teacher['department']): ?>
                                        <span class="badge badge-info"><?php echo e($teacher['department']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($teacher['username']); ?></td>
                                <td>
                                    <span class="badge badge-success"><?php echo $teacher['assigned_subjects']; ?> subjects</span>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo $teacher['total_sessions']; ?> sessions</span>
                                </td>
                                <td><?php echo formatDate($teacher['joining_date']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>No Teachers Found</h3>
                <p>No teachers match your search criteria or no teachers have been added yet.</p>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add First Teacher
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stats_sql = "SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT department) as departments
FROM TEACHERS";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Quick Statistics</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #fef3c7; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo $stats['total']; ?></div>
                <div style="color: #92400e; font-weight: 500;">Total Teachers</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo $stats['departments']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Departments</div>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>