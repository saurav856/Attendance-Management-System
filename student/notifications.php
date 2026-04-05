<?php
/**
 * Student - Notifications
 * Display low attendance notifications for the logged-in student
 */

require_once '../config.php';
checkAuth('student');
$page_title = "My Notifications";

$student_id = $_SESSION['student_id'];

// Get filter parameter
$filter_read = get('filter_read', '');

// Build query to get notifications
$sql = "SELECT 
    n.notification_id,
    n.notification_type,
    n.message,
    n.attendance_percentage,
    n.created_at,
    n.is_read,
    CONCAT(a.first_name, ' ', a.last_name) as admin_name
FROM NOTIFICATIONS n
JOIN ADMINS a ON n.admin_id = a.admin_id
WHERE n.student_id = ?";

// Apply read/unread filter
if ($filter_read !== '') {
    $sql .= " AND n.is_read = ?";
}

$sql .= " ORDER BY n.is_read ASC, n.created_at DESC";

// Prepare and execute statement
$stmt = $conn->prepare($sql);

if ($filter_read !== '') {
    $stmt->bind_param("ii", $student_id, $filter_read);
} else {
    $stmt->bind_param("i", $student_id);
}

$stmt->execute();
$result = $stmt->get_result();

// Handle mark as read action
if (isPostRequest() && isset($_POST['mark_read'])) {
    $notification_id = intval(post('notification_id'));
    
    $update_sql = "UPDATE NOTIFICATIONS SET is_read = 1 WHERE notification_id = ? AND student_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $notification_id, $student_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Notification marked as read.";
        header("Location: notifications.php");
        exit();
    }
    
    $update_stmt->close();
}

// Handle mark all as read action
if (isPostRequest() && isset($_POST['mark_all_read'])) {
    $update_all_sql = "UPDATE NOTIFICATIONS SET is_read = 1 WHERE student_id = ? AND is_read = 0";
    $update_all_stmt = $conn->prepare($update_all_sql);
    $update_all_stmt->bind_param("i", $student_id);
    
    if ($update_all_stmt->execute()) {
        $_SESSION['success_message'] = "All notifications marked as read.";
        header("Location: notifications.php");
        exit();
    }
    
    $update_all_stmt->close();
}

// Get success message if any
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-bell"></i> My Notifications</h1>
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <span>Notifications</span>
        </div>
    </div>
    <div>
        <?php if ($result->num_rows > 0): ?>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="mark_all_read" value="1">
                <button type="submit" class="btn btn-success">
                    Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Success Message -->
<?php if ($success): echo showSuccess($success); endif; ?>

<!-- Filter Section -->
<div class="search-filter-section">
    <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 200px;">
            <label for="filter_read"><i class="fas fa-filter"></i> Filter</label>
            <select id="filter_read" name="filter_read">
                <option value="">All Notifications</option>
                <option value="0" <?php echo $filter_read === '0' ? 'selected' : ''; ?>>Unread Only</option>
                <option value="1" <?php echo $filter_read === '1' ? 'selected' : ''; ?>>Read Only</option>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filter
            </button>
            <a href="notifications.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Notifications List -->
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
                    <div style="background-color: <?php echo $notification['is_read'] ? '#f9fafb' : '#fef3c7'; ?>; padding: 20px; border-radius: 8px; border-left: 4px solid <?php echo $notification['attendance_percentage'] < 60 ? '#ef4444' : '#f59e0b'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-exclamation-triangle" style="color: <?php echo $notification['attendance_percentage'] < 60 ? '#ef4444' : '#f59e0b'; ?>; font-size: 24px;"></i>
                                <div>
                                    <strong style="font-size: 16px; color: #1f2937;">
                                        <?php echo ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?>
                                    </strong>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge badge-warning" style="margin-left: 10px;">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; color: #6b7280;">
                                    <?php echo formatDateTime($notification['created_at']); ?>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" action="" style="margin-top: 5px;">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                        <button type="submit" name="mark_read" class="btn btn-sm btn-success">
                                            Mark as Read
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="margin-top: 5px;">Read</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="margin: 15px 0; padding: 15px; background-color: white; border-radius: 4px;">
                            <p style="margin: 0; color: #374151; line-height: 1.6;">
                                <?php echo e($notification['message']); ?>
                            </p>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Your Attendance:</strong> 
                                <?php echo getAttendancePercentageBadge($notification['attendance_percentage']); ?>
                            </div>
                            <div style="font-size: 12px; color: #6b7280;">
                                <i class="fas fa-user-shield"></i> From: <?php echo e($notification['admin_name']); ?>
                            </div>
                        </div>
                        
                        <?php if ($notification['attendance_percentage'] < 75): ?>
                            <div class="alert alert-warning" style="margin-top: 15px; margin-bottom: 0;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Action Required:</strong> Please improve your attendance to meet the minimum 75% requirement.
                                <a href="my_attendance.php" style="margin-left: 10px;">View My Attendance →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No Notifications</h3>
                <p>You have no notifications at this time.</p>
                <?php if ($filter_read !== ''): ?>
                    <a href="notifications.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Show All Notifications
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Card -->
<?php
// Get notification statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count
FROM NOTIFICATIONS 
WHERE student_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $student_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Notification Statistics</h2>
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
            <div style="text-align: center; padding: 15px; background-color: #d1fae5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $stats['read_count']; ?></div>
                <div style="color: #065f46; font-weight: 500;">Read Notifications</div>
            </div>
        </div>
    </div>
</div>

<!-- Information Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> About Notifications</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: 20px; line-height: 2;">
            <li><strong>Low Attendance Alerts:</strong> You receive notifications when your attendance falls below 75%</li>
            <li><strong>Attendance Percentage:</strong> Shows your current attendance percentage in the relevant subject(s)</li>
            <li><strong>Color Coding:</strong> Red border indicates critical (below 60%), orange indicates warning (60-74%)</li>
            <li>Click "Mark as Read" to acknowledge a notification</li>
            <li>Use "Mark All as Read" to clear all unread notifications at once</li>
            <li>Check your <a href="my_attendance.php">attendance records</a> for detailed information</li>
            <li>Maintain at least 75% attendance to avoid receiving these notifications</li>
        </ul>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>