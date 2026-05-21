<?php
// API for user authentication
require_once 'common.php';
require_once 'jwt_helper.php';
if (!empty(MAILGUN_API_KEY)) {
    require_once 'email_service.php';
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Password hashing function
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Password check function
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// New user registration
if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        sendError('All fields are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email address');
    }
    
    if (strlen($password) < 6) {
        sendError('Password must be at least 6 characters long');
    }
    
    // Checking if a user with this email exists
    $existingUser = fetchOne($conn, "SELECT id FROM users WHERE email = ?", [$email]);
    
    if ($existingUser) {
        sendError('An account with this email already exists');
    }
    
    // Hashing the password
    $passwordHash = hashPassword($password);
    
    // Create a user
    $userId = insertRecord($conn, 'users', [
        'email' => $email,
        'password_hash' => $passwordHash,
        'name' => $name,
        'phone' => $phone,
        'is_verified' => 0,
        'last_session' => date('Y-m-d H:i:s')
    ]);
    
    if (!$userId) {
        sendError('Failed to create user account');
    }

    if (function_exists('btb_host_chat_link_threads_to_user')) {
        btb_host_chat_link_threads_to_user($conn, (int) $userId, $email);
    }
    
    // We get the created user
    $user = fetchOne($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at FROM users WHERE id = ?", [$userId]);
    
    // Create a JWT token
    $token = createJWT([
        'user_id' => $user['id'],
        'email' => $user['email']
    ]);
    
    $cookieOpts = [
        'expires' => time() + JWT_EXPIRATION,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie('btb_auth_token', $token, $cookieOpts);
    
    if (!empty(MAILGUN_API_KEY) && function_exists('sendUserRegistrationWelcomeEmail')) {
        register_shutdown_function(static function () use ($user) {
            try {
                sendUserRegistrationWelcomeEmail($user);
            } catch (Throwable $e) {
                error_log('Auth register email error: ' . $e->getMessage());
            }
        });
    }
    
    sendSuccess([
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'is_verified' => $user['is_verified'],
            'created_at' => $user['created_at']
        ],
        'token' => $token
    ]);
}

// User Login
if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        sendError('Email and password are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email address');
    }
    
    // Getting the user
    $user = fetchOne($conn, "SELECT id, email, password_hash, name, phone, phone2, is_verified, created_at FROM users WHERE email = ?", [$email]);
    
    if (!$user) {
        sendError('No account found with this email');
    }
    
    // Checking the password
    if (!verifyPassword($password, $user['password_hash'])) {
        sendError('Incorrect password');
    }
    
    // Update last session time
    $stmt = executeQuery($conn, "UPDATE users SET last_session = NOW() WHERE id = ?", [$user['id']]);

    if (function_exists('btb_host_chat_link_threads_to_user')) {
        btb_host_chat_link_threads_to_user($conn, (int) $user['id'], (string) $user['email']);
    }
    
    // Create a JWT token
    $token = createJWT([
        'user_id' => $user['id'],
        'email' => $user['email']
    ]);
    
    $cookieOpts = [
        'expires' => time() + JWT_EXPIRATION,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie('btb_auth_token', $token, $cookieOpts);
    
    sendSuccess([
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'phone2' => $user['phone2'],
            'is_verified' => $user['is_verified'],
            'created_at' => $user['created_at']
        ],
        'token' => $token
    ]);
}

// Validating the token and retrieving user data
if ($action === 'verify') {
    $user = authenticateUser($conn);
    
    if (!$user) {
        sendError('Invalid or expired token', null, 401);
    }
    
    sendSuccess([
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'phone2' => $user['phone2'],
            'is_verified' => $user['is_verified'],
            'created_at' => $user['created_at'],
            'last_session' => $user['last_session']
        ]
    ]);
}

// User logout
if ($action === 'logout') {
    // Delete cookies
    setcookie('btb_auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    
    sendSuccess(['message' => 'Logged out successfully']);
}

// Search for user by email (to check existence)
if ($action === 'find_by_email') {
    $email = trim($_GET['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email address');
    }
    
    $user = fetchOne($conn, "SELECT id, email FROM users WHERE email = ?", [$email]);
    
    if ($user) {
        sendSuccess(['exists' => true, 'email' => $user['email']]);
    } else {
        sendSuccess(['exists' => false]);
    }
}

// Updating User Data
if ($action === 'update_profile') {
    $user = authenticateUser($conn);
    
    if (!$user) {
        sendError('Authentication required', null, 401);
    }
    
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $phone2 = trim($_POST['phone2'] ?? '');
    
    if (empty($name)) {
        sendError('Name is required');
    }
    
    // Updating the data
    $result = updateRecord($conn, 'users', [
        'name' => $name,
        'phone' => $phone,
        'phone2' => $phone2
    ], 'id = ?', [$user['id']]);
    
    if ($result === false) {
        sendError('Failed to update profile');
    }
    
    // Getting the updated user
    $updatedUser = fetchOne($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at FROM users WHERE id = ?", [$user['id']]);
    
    sendSuccess(['user' => $updatedUser]);
}

// Changing your password
if ($action === 'change_password') {
    $user = authenticateUser($conn);
    
    if (!$user) {
        sendError('Authentication required', null, 401);
    }
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        sendError('Current password and new password are required');
    }
    
    if (strlen($newPassword) < 6) {
        sendError('New password must be at least 6 characters long');
    }
    
    // Get the current password hash
    $currentUser = fetchOne($conn, "SELECT password_hash FROM users WHERE id = ?", [$user['id']]);
    
    // Checking the current password
    if (!verifyPassword($currentPassword, $currentUser['password_hash'])) {
        sendError('Current password is incorrect');
    }
    
    // Hashing the new password
    $newPasswordHash = hashPassword($newPassword);
    
    // Updating the password
    $result = updateRecord($conn, 'users', [
        'password_hash' => $newPasswordHash
    ], 'id = ?', [$user['id']]);
    
    if ($result === false) {
        sendError('Failed to change password');
    }
    
    sendSuccess(['message' => 'Password changed successfully']);
}

// Getting a list of all users (for the admin panel)
if ($action === 'get_users') {
    // TODO: Add admin authentication check here
    // For now, allow access (should be protected in production)
    
    try {
        $users = fetchAll($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at, last_session FROM users ORDER BY created_at DESC");
        
        if ($users === false) {
            sendError('Failed to fetch users');
        }

        // sendResponse puts this in top-level "data" — admin expects result.data to be the user list
        sendSuccess($users);
    } catch (Exception $e) {
        sendError('Error fetching users: ' . $e->getMessage());
    }
}

// Deleting a user (for admin)
if ($action === 'delete_user') {
    // TODO: Add admin authentication check here
    // For now, allow access (should be protected in production)
    
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        sendError('Invalid user ID');
    }
    
    try {
        // Delete user tokens (if any)
        executeQuery($conn, "DELETE FROM user_tokens WHERE user_id = ?", [$userId]);
        
        // Deleting a user
        $result = executeQuery($conn, "DELETE FROM users WHERE id = ?", [$userId]);
        
        if ($result === false) {
            sendError('Failed to delete user');
        }
        
        sendSuccess(['message' => 'User deleted successfully']);
    } catch (Exception $e) {
        sendError('Error deleting user: ' . $e->getMessage());
    }
}

// If the action is not recognized
sendError('Invalid action');

