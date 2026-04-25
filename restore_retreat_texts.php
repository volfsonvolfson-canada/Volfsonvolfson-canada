<?php
/**
 * Скрипт для восстановления полных текстов страницы Retreats and Workshops из HTML
 * Сохраняет полные тексты в базу данных, чтобы они не перезаписывались сокращенными версиями
 */

require_once 'config.php';

// Полные тексты из HTML файла retreat-and-workshop.html
$fullTexts = [
    // Hero section
    'retreat_hero_title' => 'Activities and Practices at Back to Base',
    'retreat_hero_subtitle' => 'Where nature and quiet become part of your practice',
    
    // Introduction
    'retreat_intro_text' => 'Back to Base is a place where nature and quiet become part of your practice. Everything here is designed so that any activity — from yoga to a small creative workshop — takes place in an atmosphere of calm, depth, and inspiration.',
    
    // Locations section
    'retreat_locations_title' => 'Our locations for your workshops',
    
    // Outdoor space (single description; list fields cleared)
    'retreat_forest_title' => 'Outdoor space with multi functional platforms',
    'retreat_forest_description' => "Just a few steps from the house, a winding path leads into the forest, where wooden platforms are hidden among tall trees. The air feels lighter here, the sound of the creek creates a natural meditation, and the soft light filtering through the canopy makes every practice deeper.\n\nIt's an ideal spot for:\nSunrise yoga\nEvening meditations\nBreathwork\nAny activity that benefits from a strong connection to nature",
    'retreat_forest_list_label' => '',
    'retreat_forest_list_items' => '',
    
    // Multifunctional indoor space (single description)
    'retreat_indoor_title' => 'Multifunctional indoor space',
    'retreat_indoor_description' => "Inside the house, there is a spacious room with large windows filled with light, warmth, and a sense of comfort — perfect for group gatherings, mini-lectures, workshops, breathwork sessions, or yoga during cooler weather.\n\nAnd if you need a more intimate atmosphere or plan to use visual materials, the room can easily be darkened with blackout curtains.",
    'retreat_indoor_additional' => '',
    
    // Home Theatre
    'retreat_theatre_title' => 'Cozy mini home theatre',
    'retreat_theatre_description' => 'For presentations, educational films, documentaries, or shared viewing sessions, we offer a small but very cozy home theatre. Soft lighting, quality sound, and a calm environment help create a fully immersive experience.',
    
    // Contact Form
    'retreat_contact_title' => 'Are you looking for a place to retreat or interested in joining a workshop?',
    'retreat_contact_text' => 'Just send us a message with your preferences, and we will create a program tailored specifically for you!',
    
    // Organizer
    'retreat_organizer_title' => '',
    
    // Workshops (single body; legacy list + conclusion cleared)
    'retreat_workshops_title' => '',
    'retreat_workshops_intro' => '',
    'retreat_workshops_list' => '',
    'retreat_workshops_conclusion' => '',
    
    // Collaboration (single body; legacy list + conclusion cleared)
    'retreat_collaboration_title' => 'Invitation to Collaborate',
    'retreat_collaboration_intro' => "Back to Base welcomes those who create transformative practices and help people heal and restore.\nWe are looking for:\n\nProgram creators\nYoga instructors\nMeditation teachers\nMassage therapists\nReiki practitioners\nAcupuncturists\nBody-oriented specialists\n\nIf you want to share your work in the quiet of the forest beside a mountain lake, we would be happy to collaborate with you.\nJust call or message us!",
    'retreat_collaboration_list' => '',
    'retreat_collaboration_conclusion' => ''
];

echo "<h2>Восстановление полных текстов страницы Retreats and Workshops</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .field { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; }
    .field-name { font-weight: bold; color: #333; }
    .field-value { margin-top: 5px; color: #666; font-size: 0.9em; }
</style>";

// Проверяем подключение к базе данных
if ($conn->connect_error) {
    die("<p class='error'>Ошибка подключения к базе данных: " . $conn->connect_error . "</p>");
}

// Проверяем, существует ли запись с id=1
$checkResult = $conn->query("SELECT id FROM content_settings WHERE id = 1");
if ($checkResult->num_rows === 0) {
    // Создаем запись, если её нет
    $conn->query("INSERT INTO content_settings (id) VALUES (1)");
    echo "<p class='info'>Создана новая запись в content_settings (id=1)</p>";
}

// Получаем текущие значения из базы данных
$currentResult = $conn->query("SELECT * FROM content_settings WHERE id = 1");
$currentData = $currentResult->fetch_assoc();

$updated = 0;
$skipped = 0;
$errors = [];

// Обновляем каждое поле
foreach ($fullTexts as $field => $fullText) {
    // Проверяем, существует ли колонка
    $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
    if ($columnCheck->num_rows === 0) {
        // Создаем колонку, если её нет
        $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
        if (!$conn->query($alterTableSql)) {
            $errors[] = "Ошибка создания колонки $field: " . $conn->error;
            continue;
        }
        echo "<p class='info'>Создана колонка: $field</p>";
    }
    
    // Получаем текущее значение
    $currentValue = $currentData[$field] ?? '';
    
    // Проверяем, нужно ли обновлять
    // Обновляем только если:
    // 1. Текущее значение пустое
    // 2. Текущее значение короче полного текста (сокращенная версия)
    // 3. Текущее значение содержит многоточие (признак сокращения)
    $shouldUpdate = false;
    
    if (empty($currentValue)) {
        $shouldUpdate = true;
        $reason = "пустое значение";
    } elseif (strlen($currentValue) < strlen($fullText) * 0.7) {
        // Если текущий текст значительно короче (менее 70% от полного)
        $shouldUpdate = true;
        $reason = "сокращенная версия (текущая длина: " . strlen($currentValue) . ", полная: " . strlen($fullText) . ")";
    } elseif (strpos($currentValue, '...') !== false || strpos($currentValue, '…') !== false) {
        // Если содержит многоточие (признак сокращения)
        $shouldUpdate = true;
        $reason = "содержит многоточие (сокращенная версия)";
    }
    
    if ($shouldUpdate) {
        // Обновляем значение
        $stmt = $conn->prepare("UPDATE content_settings SET $field = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param("s", $fullText);
            if ($stmt->execute()) {
                echo "<div class='field'>";
                echo "<div class='field-name'>✓ Обновлено: $field</div>";
                echo "<div class='field-value'>Причина: $reason</div>";
                echo "</div>";
                $updated++;
            } else {
                $errors[] = "Ошибка обновления $field: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Ошибка подготовки запроса для $field: " . $conn->error;
        }
    } else {
        echo "<div class='field'>";
        echo "<div class='field-name'>⊘ Пропущено: $field</div>";
        echo "<div class='field-value'>Полный текст уже сохранен (длина: " . strlen($currentValue) . ")</div>";
        echo "</div>";
        $skipped++;
    }
}

// Выводим итоги
echo "<hr>";
echo "<h3>Итоги:</h3>";
echo "<p class='success'>Обновлено полей: $updated</p>";
echo "<p class='info'>Пропущено полей (уже полные): $skipped</p>";

if (!empty($errors)) {
    echo "<h3 class='error'>Ошибки:</h3>";
    foreach ($errors as $error) {
        echo "<p class='error'>$error</p>";
    }
} else {
    echo "<p class='success'>✓ Все тексты успешно восстановлены!</p>";
}

echo "<hr>";
echo "<p><a href='admin.html#content'>Вернуться в админ-панель</a></p>";
echo "<p><strong>Важно:</strong> После проверки удалите этот файл с сервера для безопасности.</p>";

$conn->close();
?>









