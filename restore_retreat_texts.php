<?php
/**
 * Script to restore full texts of Retreats and Workshops page from HTML
 * Saves full texts to a database so they are not overwritten by shortened versions
 */

require_once 'config.php';

// Full texts from the HTML file retreat-and-workshop.html
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

echo "<h2>Restoring full page texts Retreats and Workshops</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .field { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; }
    .field-name { font-weight: bold; color: #333; }
    .field-value { margin-top: 5px; color: #666; font-size: 0.9em; }
</style>";

// Checking the connection to the database
if ($conn->connect_error) {
    die("<p class='error'>Error connecting to database: " . $conn->connect_error . "</p>");
}

// Checking if a record with id=1 exists
$checkResult = $conn->query("SELECT id FROM content_settings WHERE id = 1");
if ($checkResult->num_rows === 0) {
    // Create a record if it doesn’t exist
    $conn->query("INSERT INTO content_settings (id) VALUES (1)");
    echo "<p class='info'>A new entry has been created in content_settings (id=1)</p>";
}

// Getting current values ​​from the database
$currentResult = $conn->query("SELECT * FROM content_settings WHERE id = 1");
$currentData = $currentResult->fetch_assoc();

$updated = 0;
$skipped = 0;
$errors = [];

// Update every field
foreach ($fullTexts as $field => $fullText) {
    // Checking if a column exists
    $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
    if ($columnCheck->num_rows === 0) {
        // Create a column if it doesn’t exist
        $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
        if (!$conn->query($alterTableSql)) {
            $errors[] = "Column creation error $field: " . $conn->error;
            continue;
        }
        echo "<p class='info'>Column created: $field</p>";
    }
    
    // Get the current value
    $currentValue = $currentData[$field] ?? '';
    
    // Checking to see if it needs to be updated
    // We update only if:
    // 1. Current value is empty
    // 2. The current value is shorter than the full text (shortened version)
    // 3. The current value contains an ellipsis (abbreviation sign)
    $shouldUpdate = false;
    
    if (empty($currentValue)) {
        $shouldUpdate = true;
        $reason = "empty value";
    } elseif (strlen($currentValue) < strlen($fullText) * 0.7) {
        // If the current text is significantly shorter (less than 70% of the full text)
        $shouldUpdate = true;
        $reason = "shortened version (current length: " . strlen($currentValue) . ", full: " . strlen($fullText) . ")";
    } elseif (strpos($currentValue, '...') !== false || strpos($currentValue, '…') !== false) {
        // If contains an ellipsis (abbreviation sign)
        $shouldUpdate = true;
        $reason = "contains an ellipsis (shortened version)";
    }
    
    if ($shouldUpdate) {
        // Update the value
        $stmt = $conn->prepare("UPDATE content_settings SET $field = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param("s", $fullText);
            if ($stmt->execute()) {
                echo "<div class='field'>";
                echo "<div class='field-name'>✓ Updated: $field</div>";
                echo "<div class='field-value'>Cause: $reason</div>";
                echo "</div>";
                $updated++;
            } else {
                $errors[] = "Update error $field: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Error preparing request for $field: " . $conn->error;
        }
    } else {
        echo "<div class='field'>";
        echo "<div class='field-name'>⊘ Missed: $field</div>";
        echo "<div class='field-value'>Full text has already been saved (length: " . strlen($currentValue) . ")</div>";
        echo "</div>";
        $skipped++;
    }
}

// Let's sum it up
echo "<hr>";
echo "<h3>Results:</h3>";
echo "<p class='success'>Updated fields: $updated</p>";
echo "<p class='info'>Missing fields (already full): $skipped</p>";

if (!empty($errors)) {
    echo "<h3 class='error'>Errors:</h3>";
    foreach ($errors as $error) {
        echo "<p class='error'>$error</p>";
    }
} else {
    echo "<p class='success'>✓ All texts were successfully restored!</p>";
}

echo "<hr>";
echo "<p><a href='admin.html#content'>Return to admin panel</a></p>";
echo "<p><strong>Important:</strong> After checking, delete this file from the server for security.</p>";

$conn->close();
?>









