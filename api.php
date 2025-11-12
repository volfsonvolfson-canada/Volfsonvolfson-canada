<?php
// Отключаем вывод ошибок для API (чтобы не ломать JSON ответы)
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include common utilities
require_once 'common.php';

// Check database connection
if (!$conn) {
    sendError('Database connection failed');
}

$action = getApiAction();

// Include API handlers after action is defined
require_once 'floorplan_api.php';
require_once 'booking_api.php';
require_once 'auth_api.php';

if ($action === 'get_content') {
    try {
        $result = $conn->query("SELECT * FROM content_settings WHERE id = 1");
        
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => [
                    'homepageDescription' => $data['homepage_description'],
                    'homepageSubtitle' => $data['homepage_subtitle'],
                    'contactPhone' => $data['contact_phone'],
                    'contactEmail' => $data['contact_email'],
                    'contactAddress' => $data['contact_address'],
                    'heroImageUrl' => $data['hero_image_url'] ?? '',
                    'hero2ImageUrl' => $data['hero2_image_url'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No data found in content_settings']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_content') {
    $homepage_desc = $_POST['homepage_description'] ?? '';
    $homepage_sub = $_POST['homepage_subtitle'] ?? '';
    $phone = $_POST['contact_phone'] ?? '';
    $email = $_POST['contact_email'] ?? '';
    $address = $_POST['contact_address'] ?? '';
    $heroImage = $_POST['hero_image_url'] ?? '';
    $hero2Image = $_POST['hero2_image_url'] ?? '';
    
    $stmt = $conn->prepare("UPDATE content_settings SET 
                           homepage_description = ?, 
                           homepage_subtitle = ?, 
                           contact_phone = ?, 
                           contact_email = ?, 
                           contact_address = ?,
                           hero_image_url = ?,
                           hero2_image_url = ? 
                           WHERE id = 1");
    $stmt->bind_param("sssssss", $homepage_desc, $homepage_sub, $phone, $email, $address, $heroImage, $hero2Image);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_rooms') {
    $result = $conn->query("SELECT * FROM rooms ORDER BY created_at DESC");
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $rooms]);
    exit;
}

if ($action === 'save_room') {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $capacity = $_POST['capacity'] ?? 0;
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $room_id = $_POST['room_id'] ?? null;
    
    if ($room_id) {
        // Обновление существующей комнаты
        $stmt = $conn->prepare("UPDATE rooms SET name = ?, price = ?, capacity = ?, type = ?, description = ? WHERE id = ?");
        $stmt->bind_param("siissi", $name, $price, $capacity, $type, $description, $room_id);
    } else {
        // Создание новой комнаты
        $stmt = $conn->prepare("INSERT INTO rooms (name, price, capacity, type, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siiss", $name, $price, $capacity, $type, $description);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'delete_room') {
    $room_id = $_POST['room_id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_floorplan') {
    $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'basementSubtitle' => $data['basement_subtitle'] ?? '',
                'basementDescription' => $data['basement_description'] ?? '',
                'basementImageUrl' => $data['basement_image_url'] ?? '',
                'groundSubtitle' => $data['ground_subtitle'] ?? '',
                'groundDescription' => $data['ground_description'] ?? '',
                'groundQueenImage' => ($data['ground_image_url'] ?? $data['ground_queen_image'] ?? ''), // Universal: use ground_image_url first
                'groundTwinImage' => $data['ground_twin_image'] ?? '',
                'loftSubtitle' => $data['loft_subtitle'] ?? '',
                'loftDescription' => $data['loft_description'] ?? '',
                'loftImageUrl' => $data['loft_image_url'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No floorplan data found']);
    }
    exit;
}

if ($action === 'save_floorplan') {
    $basement_subtitle = $_POST['basementSubtitle'] ?? '';
    $basement_description = $_POST['basementDescription'] ?? '';
    $basement_image_url = $_POST['basementImageUrl'] ?? '';
    $ground_subtitle = $_POST['groundSubtitle'] ?? '';
    $ground_description = $_POST['groundDescription'] ?? '';
    // Universal: support both ground_image_url and groundQueenImage
    $ground_queen_image = $_POST['groundQueenImage'] ?? $_POST['ground_image_url'] ?? '';
    $ground_twin_image = $_POST['groundTwinImage'] ?? '';
    $loft_subtitle = $_POST['loftSubtitle'] ?? '';
    $loft_description = $_POST['loftDescription'] ?? '';
    $loft_image_url = $_POST['loftImageUrl'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (
                           id, basement_subtitle, basement_description, basement_image_url,
                           ground_subtitle, ground_description, ground_queen_image, ground_twin_image,
                           loft_subtitle, loft_description, loft_image_url
                           ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE
                           basement_subtitle = ?, basement_description = ?, basement_image_url = ?,
                           ground_subtitle = ?, ground_description = ?, ground_queen_image = ?, ground_twin_image = ?,
                           loft_subtitle = ?, loft_description = ?, loft_image_url = ?");
    $stmt->bind_param("ssssssssssssssssssss", 
        $basement_subtitle, $basement_description, $basement_image_url,
        $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
        $loft_subtitle, $loft_description, $loft_image_url,
        $basement_subtitle, $basement_description, $basement_image_url,
        $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
        $loft_subtitle, $loft_description, $loft_image_url);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
$conn->close();
?>












error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Проверка подключения
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

$action = getApiAction();

if ($action === 'get_content') {
    try {
        $result = $conn->query("SELECT * FROM content_settings WHERE id = 1");
        
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => [
                    'homepageDescription' => $data['homepage_description'],
                    'homepageSubtitle' => $data['homepage_subtitle'],
                    'contactPhone' => $data['contact_phone'],
                    'contactEmail' => $data['contact_email'],
                    'contactAddress' => $data['contact_address'],
                    'heroImageUrl' => $data['hero_image_url'] ?? '',
                    'hero2ImageUrl' => $data['hero2_image_url'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No data found in content_settings']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_content') {
    $homepage_desc = $_POST['homepage_description'] ?? '';
    $homepage_sub = $_POST['homepage_subtitle'] ?? '';
    $phone = $_POST['contact_phone'] ?? '';
    $email = $_POST['contact_email'] ?? '';
    $address = $_POST['contact_address'] ?? '';
    $heroImage = $_POST['hero_image_url'] ?? '';
    $hero2Image = $_POST['hero2_image_url'] ?? '';
    
    $stmt = $conn->prepare("UPDATE content_settings SET 
                           homepage_description = ?, 
                           homepage_subtitle = ?, 
                           contact_phone = ?, 
                           contact_email = ?, 
                           contact_address = ?,
                           hero_image_url = ?,
                           hero2_image_url = ? 
                           WHERE id = 1");
    $stmt->bind_param("sssssss", $homepage_desc, $homepage_sub, $phone, $email, $address, $heroImage, $hero2Image);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_rooms') {
    $result = $conn->query("SELECT * FROM rooms ORDER BY created_at DESC");
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $rooms]);
    exit;
}

if ($action === 'save_room') {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $capacity = $_POST['capacity'] ?? 0;
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $room_id = $_POST['room_id'] ?? null;
    
    if ($room_id) {
        // Обновление существующей комнаты
        $stmt = $conn->prepare("UPDATE rooms SET name = ?, price = ?, capacity = ?, type = ?, description = ? WHERE id = ?");
        $stmt->bind_param("siissi", $name, $price, $capacity, $type, $description, $room_id);
    } else {
        // Создание новой комнаты
        $stmt = $conn->prepare("INSERT INTO rooms (name, price, capacity, type, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siiss", $name, $price, $capacity, $type, $description);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'delete_room') {
    $room_id = $_POST['room_id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_floorplan') {
    $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'basementSubtitle' => $data['basement_subtitle'] ?? '',
                'basementDescription' => $data['basement_description'] ?? '',
                'basementImageUrl' => $data['basement_image_url'] ?? '',
                'groundSubtitle' => $data['ground_subtitle'] ?? '',
                'groundDescription' => $data['ground_description'] ?? '',
                'groundQueenImage' => ($data['ground_image_url'] ?? $data['ground_queen_image'] ?? ''), // Universal: use ground_image_url first
                'groundTwinImage' => $data['ground_twin_image'] ?? '',
                'loftSubtitle' => $data['loft_subtitle'] ?? '',
                'loftDescription' => $data['loft_description'] ?? '',
                'loftImageUrl' => $data['loft_image_url'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No floorplan data found']);
    }
    exit;
}


echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
$conn->close();
?>
















