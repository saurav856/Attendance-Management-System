<?php
require_once '../config.php';
checkAuth('admin');
$page_title = "Low Attendance Notifications";

$search = get('search', '');
$filter_read = get('filter_read', '');

$sql = "SELECT 
    n.notification_id,
    n.notification_type,
    n.message,
    n.attendance_percentage,
    n.created_at,
    n.is_read,
    s.student_id,
    s.student_card_id,
    s.first_name,
    s.last_name
FROM NOTIFICATIONS n
JOIN STUDENTS s ON n.student_id = s.student_id
WHERE n.admin_id = ?";

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_card_id LIKE ?)";
}

if ($filter_read !== '') {
    $sql .= " AND n.is_read = ?";
}

$sql .= " ORDER BY n.is_read ASC, n.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($search) && $filter_read !== '') {
    $stmt->bind_param("isssi", $_SESSION['admin_id'], $search_term, $search_term, $search_term, $filter_read);
} elseif (!empty($search)) {
    $stmt->bind_param("isss", $_SESSION['admin_id'], $search_term, $search_term, $search_term);
} elseif ($filter_read !== '') {
    $stmt->bind_param("ii", $_SESSION['admin_id'], $filter_read);
} else {
    $stmt->bind_param("i", $_SESSION['admin_id']);
}

$stmt->execute();
$result = $stmt->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-bell"></i> Low Attendance Notifications</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Notifications</span>
        </div>
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
                placeholder="Search by student name or card ID..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
            <label for="filter_read"><i class="fas fa-filter"></i> Status</label>
            <select id="filter_read" name="filter_read">
                <option value="">All Notifications</option>
                <option value="0" <?php echo $filter_read === '0' ? 'selected' : ''; ?>>Unread Only</option>
                <option value="1" <?php echo $filter_read === '1' ? 'selected' : ''; ?>>Read Only</option>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="notifications.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Notifications 
            <span class="badge badge-primary"><?php echo $result->num_rows; ?> notifications</span>
        </h2>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php while ($notification = $result->fetch_assoc()): ?>
                    <div style="background-color: <?php echo $notification['is_read'] ? '#f9fafb' : '#fef3c7'; ?>; padding: 20px; border-radius: 8px; border-left: 4px solid <?php echo $notification['is_read'] ? '#6b7280' : '#f59e0b'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-exclamation-triangle" style="color: <?php echo $notification['attendance_percentage'] < 60 ? '#ef4444' : '#f59e0b'; ?>; font-size: 24px;"></i>
                                <div>
                                    <strong style="font-size: 16px;"><?php echo e($notification['first_name'] . ' ' . $notification['last_name']); ?></strong>
                                    <span class="badge badge-secondary" style="margin-left: 10px;"><?php echo e($notification['student_card_id']); ?></span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; color: #6b7280;"><?php echo formatDateTime($notification['created_at']); ?></div>
                                <?php if ($notification['is_read']): ?>
                                    <span class="badge badge-secondary" style="margin-top: 5px;">Read</span>
                                <?php else: ?>
                                    <span class="badge badge-warning" style="margin-top: 5px;">Unread</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="margin: 15px 0; padding: 15px; background-color: white; border-radius: 4px;">
                            <p style="margin: 0; color: #374151;"><?php echo e($notification['message']); ?></p>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Attendance:</strong> 
                                <?php echo getAttendancePercentageBadge($notification['attendance_percentage']); ?>
                            </div>
                            <div>
                                <span class="badge badge-info"><?php echo ucfirst($notification['notification_type']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No Notifications</h3>
                <p>No low attendance notifications found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
FROM NOTIFICATIONS 
WHERE admin_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $_SESSION['admin_id']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Quick Statistics</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 15px; background-color: #dbeafe; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #2563eb;"><?php echo $stats['total']; ?></div>
                <div style="color: #1e40af; font-weight: 500;">Total Notifications</div>
            </div>
            <div style="text-align: center; padding: 15px; background-color: #fef3c7; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo $stats['unread']; ?></div>
                <div style="color: #92400e; font-weight: 500;">Unread Notifications</div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> About Low Attendance Alerts</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li>Notifications are automatically generated when student attendance falls below 75%</li>
            <li>Students with attendance below 60% are marked as critical (red badge)</li>
            <li>Students with attendance between 60-74% are marked as warning (orange badge)</li>
            <li>Contact students directly to discuss attendance issues</li>
            <li>View detailed attendance records in the <a href="reports.php">Reports</a> section</li>
        </ul>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>