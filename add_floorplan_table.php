<?php
// add_floorplan_table.php
// Включить отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Миграция базы данных: Добавление таблицы для Floor Plan...</h2>";

// Создаем таблицу floorplan_settings
$sql_create_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
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

if ($conn->query($sql_create_floorplan) === TRUE) {
    echo "<p style='color:green'>✓ Таблица 'floorplan_settings' успешно создана.</p>";
} else {
    echo "<p style='color:orange'>Таблица 'floorplan_settings' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Добавляем поля hero_image_url и hero2_image_url в content_settings, если их нет
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero2_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero2_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Вставляем начальные данные, если таблица пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Private top-floor space under the roof.";
    $default_loft_desc = "A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные для floorplan добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные для floorplan уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Миграция завершена!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл add_floorplan_table.php с сервера для безопасности.</p>";

$conn->close();
?>




// Включить отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Миграция базы данных: Добавление таблицы для Floor Plan...</h2>";

// Создаем таблицу floorplan_settings
$sql_create_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
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

if ($conn->query($sql_create_floorplan) === TRUE) {
    echo "<p style='color:green'>✓ Таблица 'floorplan_settings' успешно создана.</p>";
} else {
    echo "<p style='color:orange'>Таблица 'floorplan_settings' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Добавляем поля hero_image_url и hero2_image_url в content_settings, если их нет
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero2_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero2_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Вставляем начальные данные, если таблица пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Private top-floor space under the roof.";
    $default_loft_desc = "A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные для floorplan добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные для floorplan уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Миграция завершена!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл add_floorplan_table.php с сервера для безопасности.</p>";

$conn->close();
?>









// Включить отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Миграция базы данных: Добавление таблицы для Floor Plan...</h2>";

// Создаем таблицу floorplan_settings
$sql_create_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
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

if ($conn->query($sql_create_floorplan) === TRUE) {
    echo "<p style='color:green'>✓ Таблица 'floorplan_settings' успешно создана.</p>";
} else {
    echo "<p style='color:orange'>Таблица 'floorplan_settings' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Добавляем поля hero_image_url и hero2_image_url в content_settings, если их нет
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero2_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero2_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Вставляем начальные данные, если таблица пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Private top-floor space under the roof.";
    $default_loft_desc = "A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные для floorplan добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные для floorplan уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Миграция завершена!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл add_floorplan_table.php с сервера для безопасности.</p>";

$conn->close();
?>




// Включить отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Миграция базы данных: Добавление таблицы для Floor Plan...</h2>";

// Создаем таблицу floorplan_settings
$sql_create_floorplan = "CREATE TABLE IF NOT EXISTS floorplan_settings (
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

if ($conn->query($sql_create_floorplan) === TRUE) {
    echo "<p style='color:green'>✓ Таблица 'floorplan_settings' успешно создана.</p>";
} else {
    echo "<p style='color:orange'>Таблица 'floorplan_settings' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Добавляем поля hero_image_url и hero2_image_url в content_settings, если их нет
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Поле 'hero2_image_url' успешно добавлено в content_settings.</p>";
} else {
    echo "<p style='color:orange'>Поле 'hero2_image_url' уже существует или произошла ошибка: " . $conn->error . "</p>";
}

// Вставляем начальные данные, если таблица пустая
echo "<p>Проверка начальных данных...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Private top-floor space under the roof.";
    $default_loft_desc = "A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Начальные данные для floorplan добавлены</p>";
        } else {
            echo "<p style='color:red'>Ошибка при добавлении данных: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Ошибка при подготовке запроса: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Начальные данные для floorplan уже существуют</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Миграция завершена!</h1>";
echo "<p><strong>Важно:</strong> Удалите файл add_floorplan_table.php с сервера для безопасности.</p>";

$conn->close();
?>










