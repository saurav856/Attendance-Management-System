<?php
/**
 * Admin - Users List (READ)
 * Display all users in the system
 */

// Include configuration and check authentication
require_once '../config.php';
checkAuth('admin');

// Set page title
$page_title = "Manage Users";

// Handle search and filter
$search = get('search', '');
$filter_type = get('filter_type', '');

// Build query
$sql = "SELECT 
    u.user_id,
    u.username,
    u.user_type,
    u.created_at,
    u.last_login,
    u.is_active,
    CASE 
        WHEN u.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
        WHEN u.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
        WHEN u.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
        ELSE 'N/A'
    END as full_name,
    CASE 
        WHEN u.user_type = 'student' THEN s.email
        WHEN u.user_type = 'teacher' THEN t.email
        WHEN u.user_type = 'admin' THEN a.email
        ELSE NULL
    END as email
FROM USERS u
LEFT JOIN STUDENTS s ON u.user_id = s.user_id AND u.user_type = 'student'
LEFT JOIN TEACHERS t ON u.user_id = t.user_id AND u.user_type = 'teacher'
LEFT JOIN ADMINS a ON u.user_id = a.user_id AND u.user_type = 'admin'
WHERE 1=1";

// Apply search filter
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (u.username LIKE ? OR 
              CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR
              CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR
              CONCAT(a.first_name, ' ', a.last_name) LIKE ?)";
}

// Apply user type filter
if (!empty($filter_type)) {
    $sql .= " AND u.user_type = ?";
}

$sql .= " ORDER BY u.created_at DESC";

// Prepare and execute statement
$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_type)) {
    $stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $filter_type);
} elseif (!empty($search)) {
    $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
} elseif (!empty($filter_type)) {
    $stmt->bind_param("s", $filter_type);
}

$stmt->execute();
$result = $stmt->get_result();

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-users"></i> Manage Users</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Users</span>
        </div>
    </div>
    <div>
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New User
        </a>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="search-filter-section">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end; flex-wrap:wrap;">
        <div class="form-group" style="flex: 2; margin-bottom: 0; min-width:250px;">
            <label for="search"><i class="fas fa-search"></i> Search</label>
            <input 
                type="text" 
                id="search" 
                name="search" 
                placeholder="Search by username or name..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label for="filter_type"><i class="fas fa-filter"></i> User Type</label>
            <select id="filter_type" name="filter_type">
                <option value="">All Types</option>
                <option value="admin" <?php echo $filter_type == 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="teacher" <?php echo $filter_type == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                <option value="student" <?php echo $filter_type == 'student' ? 'selected' : ''; ?>>Student</option>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="list_users.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Users List Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Users List 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> users</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>User Type</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td>
                                    <strong><?php echo e($user['username']); ?></strong>
                                </td>
                                <td><?php echo e($user['full_name']); ?></td>
                                <td><?php echo $user['email'] ? e($user['email']) : '<span class="text-muted">N/A</span>'; ?></td>
                                <td><?php echo getUserTypeBadge($user['user_type']); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <?php 
                                    if ($user['last_login']) {
                                        echo formatDateTime($user['last_login']);
                                    } else {
                                        echo '<span class="text-muted">Never</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_user.php?id=<?php echo $user['user_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           title="Delete User"
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
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
                <i class="fas fa-users-slash"></i>
                <h3>No Users Found</h3>
                <p>No users match your search criteria.</p>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add First User
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Close statement
$stmt->close();

// Include footer
include '../includes/footer.php';
?>