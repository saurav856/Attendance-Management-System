<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Manage Subjects";

$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$search = get('search', '');

$sql = "SELECT 
    s.subject_id,
    s.subject_code,
    s.subject_name,
    s.credit_hours,
    s.description,
    COUNT(DISTINCT ss.section_subject_id) as assigned_count,
    COUNT(DISTINCT ss.section_id) as sections_count
FROM SUBJECTS s
LEFT JOIN SECTION_SUBJECTS ss ON s.subject_id = ss.subject_id
WHERE 1=1";

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (s.subject_code LIKE ? OR s.subject_name LIKE ? OR s.description LIKE ?)";
}

$sql .= " GROUP BY s.subject_id ORDER BY s.subject_code ASC";

$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
}

$stmt->execute();
$result = $stmt->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-book"></i> Manage Subjects</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Subjects</span>
        </div>
    </div>
    <div>
        <a href="add_subject.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add New Subject
        </a>
    </div>
</div>

<?php if ($error): echo showError($error); endif; ?>
<?php if ($success): echo showSuccess($success); endif; ?>

<div class="search-filter-section">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end;">
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label for="search"><i class="fas fa-search"></i> Search</label>
            <input 
                type="text" 
                id="search" 
                name="search" 
                placeholder="Search by code, name, or description..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="list_subjects.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Subjects List 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> subjects</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Credit Hours</th>
                            <th>Description</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($subject = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong style="color: #2563eb;"><?php echo e($subject['subject_code']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo e($subject['subject_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $subject['credit_hours']; ?> hrs</span>
                                </td>
                                <td>
                                    <?php if ($subject['description']): ?>
                                        <?php echo e(substr($subject['description'], 0, 60)) . (strlen($subject['description']) > 60 ? '...' : ''); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No description</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-success"><?php echo $subject['sections_count']; ?> section(s)</span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit_subject.php?id=<?php echo $subject['subject_id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Edit Subject">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_subject.php?id=<?php echo $subject['subject_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           title="Delete Subject"
                                           onclick="return confirm('Are you sure you want to delete this subject?');">
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
                <i class="fas fa-book"></i>
                <h3>No Subjects Found</h3>
                <p>No subjects match your search or no subjects have been created yet.</p>
                <a href="add_subject.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Add First Subject
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(credit_hours) as total_credits
FROM SUBJECTS";
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
                <div style="color: #1e40af; font-weight: 500;">Total Subjects</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $stats['total_credits']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Total Credit Hours</div>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>