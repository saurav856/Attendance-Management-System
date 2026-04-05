<?php
/**
 * Admin - Students List (READ)
 * Display all students in the system
 */

// Include configuration and check authentication
require_once '../config.php';
checkAuth('admin');

// Set page title
$page_title = "Manage Students";

// Handle success/error messages from session
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle search and filter
$search = get('search', '');
$filter_status = get('filter_status', '');
$filter_section = get('filter_section', '');

// Build query
$sql = "SELECT 
    s.student_id,
    s.user_id,
    s.student_card_id,
    s.first_name,
    s.last_name,
    s.email,
    s.phone,
    s.enrollment_date,
    s.status,
    u.username,
    u.is_active,
    GROUP_CONCAT(DISTINCT sec.section_name SEPARATOR ', ') as sections,
    COUNT(DISTINCT e.enrollment_id) as enrollment_count
FROM STUDENTS s
JOIN USERS u ON s.user_id = u.user_id
LEFT JOIN ENROLLMENTS e ON s.student_id = e.student_id AND e.status = 'active'
LEFT JOIN SECTIONS sec ON e.section_id = sec.section_id
WHERE 1=1";

// Apply search filter
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_card_id LIKE ? OR s.email LIKE ? OR u.username LIKE ?)";
}

// Apply status filter
if (!empty($filter_status)) {
    $sql .= " AND s.status = ?";
}

// Apply section filter
if (!empty($filter_section)) {
    $sql .= " AND e.section_id = ?";
}

$sql .= " GROUP BY s.student_id ORDER BY s.enrollment_date DESC, s.last_name ASC";

// Prepare and execute statement
$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_status) && !empty($filter_section)) {
    $stmt->bind_param("ssssssi", $search_term, $search_term, $search_term, $search_term, $search_term, $filter_status, $filter_section);
} elseif (!empty($search) && !empty($filter_status)) {
    $stmt->bind_param("ssssss", $search_term, $search_term, $search_term, $search_term, $search_term, $filter_status);
} elseif (!empty($search) && !empty($filter_section)) {
    $stmt->bind_param("ssssssi", $search_term, $search_term, $search_term, $search_term, $search_term, $filter_section);
} elseif (!empty($search)) {
    $stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $search_term);
} elseif (!empty($filter_status) && !empty($filter_section)) {
    $stmt->bind_param("si", $filter_status, $filter_section);
} elseif (!empty($filter_status)) {
    $stmt->bind_param("s", $filter_status);
} elseif (!empty($filter_section)) {
    $stmt->bind_param("i", $filter_section);
}

$stmt->execute();
$result = $stmt->get_result();

// Get all sections for filter dropdown
$sections_sql = "SELECT section_id, section_name FROM SECTIONS WHERE is_active = 1 ORDER BY section_name";
$sections_result = $conn->query($sections_sql);

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-graduate"></i> Manage Students</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Students</span>
        </div>
    </div>
    <div>
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New Student
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): ?>
    <?php echo showError($error); ?>
<?php endif; ?>

<?php if ($success): ?>
    <?php echo showSuccess($success); ?>
<?php endif; ?>

<!-- Search and Filter Section -->
<div class="search-filter-section">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 2; margin-bottom: 0; min-width: 250px;">
            <label for="search"><i class="fas fa-search"></i> Search</label>
            <input 
                type="text" 
                id="search" 
                name="search" 
                placeholder="Search by name, card ID, email, or username..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
            <label for="filter_status"><i class="fas fa-filter"></i> Status</label>
            <select id="filter_status" name="filter_status">
                <option value="">All Statuses</option>
                <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="graduated" <?php echo $filter_status == 'graduated' ? 'selected' : ''; ?>>Graduated</option>
            </select>
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
            <label for="filter_section"><i class="fas fa-layer-group"></i> Section</label>
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
            <a href="list_students.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Students List Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Students List 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> students</span>
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
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Username</th>
                            <th>Sections</th>
                            <th>Status</th>
                            <th>Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($student['student_card_id']); ?></strong>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-circle" style="color: #2563eb; font-size: 20px;"></i>
                                        <div>
                                            <strong><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $student['email'] ? e($student['email']) : '<span class="text-muted">N/A</span>'; ?></td>
                                <td><?php echo $student['phone'] ? e($student['phone']) : '<span class="text-muted">N/A</span>'; ?></td>
                                <td><?php echo e($student['username']); ?></td>
                                <td>
                                    <?php if ($student['sections']): ?>
                                        <span class="badge badge-info"><?php echo e($student['sections']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not enrolled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getStatusBadge($student['status']); ?></td>
                                <td><?php echo formatDate($student['enrollment_date']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <h3>No Students Found</h3>
                <p>No students match your search criteria or no students have been added yet.</p>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add First Student
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Statistics -->
<?php
// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
    SUM(CASE WHEN status = 'graduated' THEN 1 ELSE 0 END) as graduated_count
FROM STUDENTS";
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
                <div style="color: #1e40af; font-weight: 500;">Total Students</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $stats['active_count']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Active</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #f3f4f6; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #6b7280;"><?php echo $stats['inactive_count']; ?></div>
                <div style="color: #374151; font-weight: 500;">Inactive</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo $stats['graduated_count']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Graduated</div>
            </div>
        </div>
    </div>
</div>

<?php
// Close statement
$stmt->close();

// Include footer
include '../includes/footer.php';
?>