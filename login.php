<?php


require_once 'config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
$success = '';

// Handle login form submission
if (isPostRequest()) {
    $username = sanitize(post('username'));
    $password = post('password');
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Query to get user
        $sql = "SELECT u.user_id, u.username, u.password_hash, u.user_type, u.is_active 
                FROM USERS u 
                WHERE u.username = ? 
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if ($user['is_active'] != 1) {
                $error = "Your account has been deactivated. Please contact administration.";
            } 
            // Verify password
            else if (password_verify($password, $user['password_hash'])) {
                // Password is correct, create session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Update last login
                $update_sql = "UPDATE USERS SET last_login = NOW() WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                
                // Get additional user info based on type
                if ($user['user_type'] == 'student') {
                    $info_sql = "SELECT student_id, first_name, last_name FROM STUDENTS WHERE user_id = ?";
                    $info_stmt = $conn->prepare($info_sql);
                    $info_stmt->bind_param("i", $user['user_id']);
                    $info_stmt->execute();
                    $info_result = $info_stmt->get_result();
                    $info = $info_result->fetch_assoc();
                    $_SESSION['student_id'] = $info['student_id'];
                    $_SESSION['full_name'] = $info['first_name'] . ' ' . $info['last_name'];
                } 
                else if ($user['user_type'] == 'teacher') {
                    $info_sql = "SELECT teacher_id, first_name, last_name FROM TEACHERS WHERE user_id = ?";
                    $info_stmt = $conn->prepare($info_sql);
                    $info_stmt->bind_param("i", $user['user_id']);
                    $info_stmt->execute();
                    $info_result = $info_stmt->get_result();
                    $info = $info_result->fetch_assoc();
                    $_SESSION['teacher_id'] = $info['teacher_id'];
                    $_SESSION['full_name'] = $info['first_name'] . ' ' . $info['last_name'];
                } 
                else if ($user['user_type'] == 'admin') {
                    $info_sql = "SELECT admin_id, first_name, last_name FROM ADMINS WHERE user_id = ?";
                    $info_stmt = $conn->prepare($info_sql);
                    $info_stmt->bind_param("i", $user['user_id']);
                    $info_stmt->execute();
                    $info_result = $info_stmt->get_result();
                    $info = $info_result->fetch_assoc();
                    $_SESSION['admin_id'] = $info['admin_id'];
                    $_SESSION['full_name'] = $info['first_name'] . ' ' . $info['last_name'];
                }
                
                // Redirect to appropriate dashboard
                redirectToDashboard();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Attendance Management System</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- font family -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap" style="font-size: 50px; color: #2563eb;"></i>
                </div>
                <h1>Attendify</h1>
                <p>Login to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter your username" 
                        required 
                        autofocus
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>
        </div>
    </div>
</body>
</html>