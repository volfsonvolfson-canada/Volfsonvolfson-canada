<?php
// Включить отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Инициализация базы данных...</h2>";

// Комнаты
$sql_rooms = "CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    capacity INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги массажа
$sql_massage = "CREATE TABLE IF NOT EXISTS massage_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги йоги
$sql_yoga = "CREATE TABLE IF NOT EXISTS yoga_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Контент
$sql_content = "CREATE TABLE IF NOT EXISTS content_settings (
    id INT PRIMARY KEY,
    homepage_description TEXT,
    homepage_subtitle TEXT,
    contact_phone VARCHAR(50),
    contact_email VARCHAR(100),
    contact_address VARCHAR(255),
    hero_image_url VARCHAR(255),
    hero2_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Floorplan
$sql_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
    id INT PRIMARY KEY,
    basement_subtitle VARCHAR(255),
    basement_description TEXT,
    basement_image_url VARCHAR(255),
    ground_subtitle VARCHAR(255),
    ground_description TEXT,
    ground_queen_image VARCHAR(255),
    ground_twin_image VARCHAR(255),
    loft_subtitle VARCHAR(255),
    loft_description TEXT,
    loft_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Выполняем запросы
echo "<p>Создание таблицы rooms...</p>";
$result = $conn->query($sql_rooms);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании rooms: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица rooms создана</p>";
}

echo "<p>Создание таблицы massage_services...</p>";
$result = $conn->query($sql_massage);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании massage_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица massage_services создана</p>";
}

echo "<p>Создание таблицы yoga_services...</p>";
$result = $conn->query($sql_yoga);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании yoga_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица yoga_services создана</p>";
}

echo "<p>Создание таблицы content_settings...</p>";
$result = $conn->query($sql_content);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании content_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица content_settings создана</p>";
}

echo "<p>Создание таблицы floorplan_settings...</p>";
$result = $conn->query($sql_floorplan);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании floorplan_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица floorplan_settings создана</p>";
}

// Вставляем начальные данные, если таблица контента пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM content_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_desc = "Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.";
    $default_subtitle = "Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.";
    $default_phone = "+1 (555) 123‑4567";
    $default_email = "hello@backtobase.example";
    $default_address = "British Columbia, Canada";
    
    $stmt = $conn->prepare("INSERT INTO content_settings (id, homepage_description, homepage_subtitle, contact_phone, contact_email, contact_address) VALUES (1, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $default_desc, $default_subtitle, $default_phone, $default_email, $default_address);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>База данных создана успешно!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл init_database.php с сервера для безопасности.</p>";
echo "<p><a href='index.html'>Вернуться на главную</a></p>";

$conn->close();
?>



error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Инициализация базы данных...</h2>";

// Комнаты
$sql_rooms = "CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    capacity INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги массажа
$sql_massage = "CREATE TABLE IF NOT EXISTS massage_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги йоги
$sql_yoga = "CREATE TABLE IF NOT EXISTS yoga_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Контент
$sql_content = "CREATE TABLE IF NOT EXISTS content_settings (
    id INT PRIMARY KEY,
    homepage_description TEXT,
    homepage_subtitle TEXT,
    contact_phone VARCHAR(50),
    contact_email VARCHAR(100),
    contact_address VARCHAR(255),
    hero_image_url VARCHAR(255),
    hero2_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Floorplan
$sql_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
    id INT PRIMARY KEY,
    basement_subtitle VARCHAR(255),
    basement_description TEXT,
    basement_image_url VARCHAR(255),
    ground_subtitle VARCHAR(255),
    ground_description TEXT,
    ground_queen_image VARCHAR(255),
    ground_twin_image VARCHAR(255),
    loft_subtitle VARCHAR(255),
    loft_description TEXT,
    loft_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Выполняем запросы
echo "<p>Создание таблицы rooms...</p>";
$result = $conn->query($sql_rooms);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании rooms: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица rooms создана</p>";
}

echo "<p>Создание таблицы massage_services...</p>";
$result = $conn->query($sql_massage);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании massage_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица massage_services создана</p>";
}

echo "<p>Создание таблицы yoga_services...</p>";
$result = $conn->query($sql_yoga);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании yoga_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица yoga_services создана</p>";
}

echo "<p>Создание таблицы content_settings...</p>";
$result = $conn->query($sql_content);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании content_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица content_settings создана</p>";
}

echo "<p>Создание таблицы floorplan_settings...</p>";
$result = $conn->query($sql_floorplan);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании floorplan_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица floorplan_settings создана</p>";
}

// Вставляем начальные данные, если таблица контента пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM content_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_desc = "Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.";
    $default_subtitle = "Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.";
    $default_phone = "+1 (555) 123‑4567";
    $default_email = "hello@backtobase.example";
    $default_address = "British Columbia, Canada";
    
    $stmt = $conn->prepare("INSERT INTO content_settings (id, homepage_description, homepage_subtitle, contact_phone, contact_email, contact_address) VALUES (1, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $default_desc, $default_subtitle, $default_phone, $default_email, $default_address);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>База данных создана успешно!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл init_database.php с сервера для безопасности.</p>";
echo "<p><a href='index.html'>Вернуться на главную</a></p>";

$conn->close();
?>








error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Инициализация базы данных...</h2>";

// Комнаты
$sql_rooms = "CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    capacity INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги массажа
$sql_massage = "CREATE TABLE IF NOT EXISTS massage_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги йоги
$sql_yoga = "CREATE TABLE IF NOT EXISTS yoga_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Контент
$sql_content = "CREATE TABLE IF NOT EXISTS content_settings (
    id INT PRIMARY KEY,
    homepage_description TEXT,
    homepage_subtitle TEXT,
    contact_phone VARCHAR(50),
    contact_email VARCHAR(100),
    contact_address VARCHAR(255),
    hero_image_url VARCHAR(255),
    hero2_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Floorplan
$sql_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
    id INT PRIMARY KEY,
    basement_subtitle VARCHAR(255),
    basement_description TEXT,
    basement_image_url VARCHAR(255),
    ground_subtitle VARCHAR(255),
    ground_description TEXT,
    ground_queen_image VARCHAR(255),
    ground_twin_image VARCHAR(255),
    loft_subtitle VARCHAR(255),
    loft_description TEXT,
    loft_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Выполняем запросы
echo "<p>Создание таблицы rooms...</p>";
$result = $conn->query($sql_rooms);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании rooms: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица rooms создана</p>";
}

echo "<p>Создание таблицы massage_services...</p>";
$result = $conn->query($sql_massage);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании massage_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица massage_services создана</p>";
}

echo "<p>Создание таблицы yoga_services...</p>";
$result = $conn->query($sql_yoga);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании yoga_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица yoga_services создана</p>";
}

echo "<p>Создание таблицы content_settings...</p>";
$result = $conn->query($sql_content);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании content_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица content_settings создана</p>";
}

echo "<p>Создание таблицы floorplan_settings...</p>";
$result = $conn->query($sql_floorplan);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании floorplan_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица floorplan_settings создана</p>";
}

// Вставляем начальные данные, если таблица контента пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM content_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_desc = "Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.";
    $default_subtitle = "Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.";
    $default_phone = "+1 (555) 123‑4567";
    $default_email = "hello@backtobase.example";
    $default_address = "British Columbia, Canada";
    
    $stmt = $conn->prepare("INSERT INTO content_settings (id, homepage_description, homepage_subtitle, contact_phone, contact_email, contact_address) VALUES (1, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $default_desc, $default_subtitle, $default_phone, $default_email, $default_address);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>База данных создана успешно!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл init_database.php с сервера для безопасности.</p>";
echo "<p><a href='index.html'>Вернуться на главную</a></p>";

$conn->close();
?>



error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Инициализация базы данных...</h2>";

// Комнаты
$sql_rooms = "CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    capacity INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги массажа
$sql_massage = "CREATE TABLE IF NOT EXISTS massage_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Услуги йоги
$sql_yoga = "CREATE TABLE IF NOT EXISTS yoga_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT NOT NULL,
    price INT NOT NULL,
    type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Контент
$sql_content = "CREATE TABLE IF NOT EXISTS content_settings (
    id INT PRIMARY KEY,
    homepage_description TEXT,
    homepage_subtitle TEXT,
    contact_phone VARCHAR(50),
    contact_email VARCHAR(100),
    contact_address VARCHAR(255),
    hero_image_url VARCHAR(255),
    hero2_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Floorplan
$sql_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
    id INT PRIMARY KEY,
    basement_subtitle VARCHAR(255),
    basement_description TEXT,
    basement_image_url VARCHAR(255),
    ground_subtitle VARCHAR(255),
    ground_description TEXT,
    ground_queen_image VARCHAR(255),
    ground_twin_image VARCHAR(255),
    loft_subtitle VARCHAR(255),
    loft_description TEXT,
    loft_image_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Выполняем запросы
echo "<p>Создание таблицы rooms...</p>";
$result = $conn->query($sql_rooms);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании rooms: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица rooms создана</p>";
}

echo "<p>Создание таблицы massage_services...</p>";
$result = $conn->query($sql_massage);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании massage_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица massage_services создана</p>";
}

echo "<p>Создание таблицы yoga_services...</p>";
$result = $conn->query($sql_yoga);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании yoga_services: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица yoga_services создана</p>";
}

echo "<p>Создание таблицы content_settings...</p>";
$result = $conn->query($sql_content);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании content_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица content_settings создана</p>";
}

echo "<p>Создание таблицы floorplan_settings...</p>";
$result = $conn->query($sql_floorplan);
if (!$result) {
    echo "<p style='color:red'>Ошибка при создании floorplan_settings: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green'>✓ Таблица floorplan_settings создана</p>";
}

// Вставляем начальные данные, если таблица контента пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM content_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_desc = "Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.";
    $default_subtitle = "Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.";
    $default_phone = "+1 (555) 123‑4567";
    $default_email = "hello@backtobase.example";
    $default_address = "British Columbia, Canada";
    
    $stmt = $conn->prepare("INSERT INTO content_settings (id, homepage_description, homepage_subtitle, contact_phone, contact_email, contact_address) VALUES (1, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $default_desc, $default_subtitle, $default_phone, $default_email, $default_address);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>База данных создана успешно!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл init_database.php с сервера для безопасности.</p>";
echo "<p><a href='index.html'>Вернуться на главную</a></p>";

$conn->close();
?>









