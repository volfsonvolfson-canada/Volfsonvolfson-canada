<?php
/**
 * Скрипт для удаления всех бронирований из базы данных
 * ВНИМАНИЕ: Этот скрипт удалит ВСЕ бронирования без возможности восстановления!
 * 
 * Использование:
 * 1. Загрузите этот файл на хостинг
 * 2. Откройте в браузере: https://new.backtobase.ca/delete_all_bookings.php
 * 3. После выполнения удалите файл с хостинга для безопасности
 */

require_once 'config.php';
require_once 'common.php';

// Используем глобальное подключение $conn из config.php
// Проверка авторизации администратора (опционально, раскомментируйте если нужно)
// if (!isAdminAuthenticated()) {
//     die('Admin authentication required');
// }

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete All Bookings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #e53e3e;
            margin-bottom: 20px;
        }
        .warning {
            background: #fed7d7;
            border: 2px solid #e53e3e;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #c53030;
        }
        .info {
            background: #bee3f8;
            border: 1px solid #3182ce;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #2c5282;
        }
        .success {
            background: #c6f6d5;
            border: 1px solid #38a169;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #2f855a;
        }
        .error {
            background: #fed7d7;
            border: 1px solid #e53e3e;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #c53030;
        }
        button {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background: #c53030;
        }
        .stats {
            margin-top: 20px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 6px;
        }
        .stats p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Delete All Bookings</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
            try {
                // Используем глобальное подключение $conn из config.php
                global $conn;
                
                if (!$conn || $conn->connect_error) {
                    throw new Exception('Failed to connect to database: ' . ($conn->connect_error ?? 'Unknown error'));
                }
                
                // Получаем количество бронирований перед удалением
                $countBefore = fetchOne($conn, "SELECT COUNT(*) as count FROM bookings");
                $countBefore = $countBefore['count'] ?? 0;
                
                // Удаляем связанные записи из booking_confirmations
                $deleteConfirmations = executeQuery($conn, "DELETE FROM booking_confirmations");
                
                // Удаляем все бронирования
                $deleteBookings = executeQuery($conn, "DELETE FROM bookings");
                
                // Проверяем результат
                $countAfter = fetchOne($conn, "SELECT COUNT(*) as count FROM bookings");
                $countAfter = $countAfter['count'] ?? 0;
                
                // Не закрываем соединение, так как оно глобальное
                
                echo '<div class="success">';
                echo '<h2>✅ Success!</h2>';
                echo '<p><strong>All bookings have been deleted successfully.</strong></p>';
                echo '<p>Bookings deleted: ' . $countBefore . '</p>';
                echo '<p>Remaining bookings: ' . $countAfter . '</p>';
                echo '</div>';
                
                echo '<div class="info">';
                echo '<p><strong>Important:</strong> Please delete this file (delete_all_bookings.php) from your hosting for security reasons.</p>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h2>❌ Error</h2>';
                echo '<p>Failed to delete bookings: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        } else {
            // Показываем форму подтверждения
            try {
                // Используем глобальное подключение $conn из config.php
                global $conn;
                
                if ($conn && !$conn->connect_error) {
                    $count = fetchOne($conn, "SELECT COUNT(*) as count FROM bookings");
                    $count = $count['count'] ?? 0;
                } else {
                    $count = 'unknown';
                }
            } catch (Exception $e) {
                $count = 'unknown';
            }
            
            echo '<div class="warning">';
            echo '<h2>⚠️ WARNING</h2>';
            echo '<p><strong>This action will PERMANENTLY DELETE ALL bookings from the database!</strong></p>';
            echo '<p>This includes:</p>';
            echo '<ul>';
            echo '<li>All booking records</li>';
            echo '<li>All confirmation codes</li>';
            echo '<li>All booking history</li>';
            echo '</ul>';
            echo '<p><strong>This action CANNOT be undone!</strong></p>';
            echo '</div>';
            
            if ($count !== 'unknown') {
                echo '<div class="info">';
                echo '<p><strong>Current bookings in database: ' . $count . '</strong></p>';
                echo '</div>';
            }
            ?>
            
            <form method="POST" onsubmit="return confirm('Are you ABSOLUTELY SURE you want to delete ALL bookings? This action cannot be undone!');">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit">Delete All Bookings</button>
            </form>
            
            <?php
        }
        ?>
    </div>
</body>
</html>

