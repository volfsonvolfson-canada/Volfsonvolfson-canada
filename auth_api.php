<?php
// API для аутентификации пользователей
require_once 'common.php';
require_once 'jwt_helper.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Функция для хеширования пароля
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Функция для проверки пароля
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Регистрация нового пользователя
if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Валидация
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        sendError('All fields are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email address');
    }
    
    if (strlen($password) < 6) {
        sendError('Password must be at least 6 characters long');
    }
    
    // Проверяем, существует ли пользователь с таким email
    $existingUser = fetchOne($conn, "SELECT id FROM users WHERE email = ?", [$email]);
    
    if ($existingUser) {
        sendError('An account with this email already exists');
    }
    
    // Хешируем пароль
    $passwordHash = hashPassword($password);
    
    // Создаем пользователя
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
    
    // Получаем созданного пользователя
    $user = fetchOne($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at FROM users WHERE id = ?", [$userId]);
    
    // Создаем JWT токен
    $token = createJWT([
        'user_id' => $user['id'],
        'email' => $user['email']
    ]);
    
    // Устанавливаем cookie с токеном
    setcookie('btb_auth_token', $token, time() + JWT_EXPIRATION, '/', '', true, true); // secure, httponly
    
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

// Вход пользователя
if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Валидация
    if (empty($email) || empty($password)) {
        sendError('Email and password are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email address');
    }
    
    // Получаем пользователя
    $user = fetchOne($conn, "SELECT id, email, password_hash, name, phone, phone2, is_verified, created_at FROM users WHERE email = ?", [$email]);
    
    if (!$user) {
        sendError('No account found with this email');
    }
    
    // Проверяем пароль
    if (!verifyPassword($password, $user['password_hash'])) {
        sendError('Incorrect password');
    }
    
    // Обновляем время последнего сеанса
    $stmt = executeQuery($conn, "UPDATE users SET last_session = NOW() WHERE id = ?", [$user['id']]);
    
    // Создаем JWT токен
    $token = createJWT([
        'user_id' => $user['id'],
        'email' => $user['email']
    ]);
    
    // Устанавливаем cookie с токеном
    setcookie('btb_auth_token', $token, time() + JWT_EXPIRATION, '/', '', true, true); // secure, httponly
    
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

// Проверка токена и получение данных пользователя
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

// Выход пользователя
if ($action === 'logout') {
    // Удаляем cookie
    setcookie('btb_auth_token', '', time() - 3600, '/', '', true, true);
    
    sendSuccess(['message' => 'Logged out successfully']);
}

// Поиск пользователя по email (для проверки существования)
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

// Обновление данных пользователя
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
    
    // Обновляем данные
    $result = updateRecord($conn, 'users', [
        'name' => $name,
        'phone' => $phone,
        'phone2' => $phone2
    ], 'id = ?', [$user['id']]);
    
    if ($result === false) {
        sendError('Failed to update profile');
    }
    
    // Получаем обновленного пользователя
    $updatedUser = fetchOne($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at FROM users WHERE id = ?", [$user['id']]);
    
    sendSuccess(['user' => $updatedUser]);
}

// Изменение пароля
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
    
    // Получаем текущий хеш пароля
    $currentUser = fetchOne($conn, "SELECT password_hash FROM users WHERE id = ?", [$user['id']]);
    
    // Проверяем текущий пароль
    if (!verifyPassword($currentPassword, $currentUser['password_hash'])) {
        sendError('Current password is incorrect');
    }
    
    // Хешируем новый пароль
    $newPasswordHash = hashPassword($newPassword);
    
    // Обновляем пароль
    $result = updateRecord($conn, 'users', [
        'password_hash' => $newPasswordHash
    ], 'id = ?', [$user['id']]);
    
    if ($result === false) {
        sendError('Failed to change password');
    }
    
    sendSuccess(['message' => 'Password changed successfully']);
}

// Получение списка всех пользователей (для админки)
if ($action === 'get_users') {
    // TODO: Add admin authentication check here
    // For now, allow access (should be protected in production)
    
    try {
        $users = fetchAll($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at, last_session FROM users ORDER BY created_at DESC");
        
        if ($users === false) {
            sendError('Failed to fetch users');
        }
        
        sendSuccess(['data' => $users]);
    } catch (Exception $e) {
        sendError('Error fetching users: ' . $e->getMessage());
    }
}

// Удаление пользователя (для админки)
if ($action === 'delete_user') {
    // TODO: Add admin authentication check here
    // For now, allow access (should be protected in production)
    
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        sendError('Invalid user ID');
    }
    
    try {
        // Удаляем токены пользователя (если есть)
        executeQuery($conn, "DELETE FROM user_tokens WHERE user_id = ?", [$userId]);
        
        // Удаляем пользователя
        $result = executeQuery($conn, "DELETE FROM users WHERE id = ?", [$userId]);
        
        if ($result === false) {
            sendError('Failed to delete user');
        }
        
        sendSuccess(['message' => 'User deleted successfully']);
    } catch (Exception $e) {
        sendError('Error deleting user: ' . $e->getMessage());
    }
}

// Если действие не распознано
sendError('Invalid action');

