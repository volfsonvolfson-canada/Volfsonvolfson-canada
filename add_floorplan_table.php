<?php
// add_floorplan_table.php
// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Database migration: Adding a table for Floor Plan...</h2>";

// Create the floorplan_settings table
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
    echo "<p style='color:green'>✓ Table 'floorplan_settings' successfully created.</p>";
} else {
    echo "<p style='color:orange'>Table 'floorplan_settings' already exists or an error occurred: " . $conn->error . "</p>";
}

// Add the fields hero_image_url and hero2_image_url to content_settings if they are not there
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero2_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero2_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

// Insert initial data if the table is empty
echo "<p>Checking initial data...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Multifunctional spaces & small cinema";
    $default_loft_desc = "Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Initial data for floorplan added</p>";
        } else {
            echo "<p style='color:red'>Error adding data: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Error preparing request: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Initial data for floorplan already exist</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Migration complete!</h1>";
echo "<p><strong>Important:</strong> Delete the file add_floorplan_table.php from the server for security.</p>";

$conn->close();
?>




// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Database migration: Adding a table for Floor Plan...</h2>";

// Create the floorplan_settings table
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
    echo "<p style='color:green'>✓ Table 'floorplan_settings' successfully created.</p>";
} else {
    echo "<p style='color:orange'>Table 'floorplan_settings' already exists or an error occurred: " . $conn->error . "</p>";
}

// Add the fields hero_image_url and hero2_image_url to content_settings if they are not there
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero2_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero2_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

// Insert initial data if the table is empty
echo "<p>Checking initial data...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Multifunctional spaces & small cinema";
    $default_loft_desc = "Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Initial data for floorplan added</p>";
        } else {
            echo "<p style='color:red'>Error adding data: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Error preparing request: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Initial data for floorplan already exist</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Migration complete!</h1>";
echo "<p><strong>Important:</strong> Delete the file add_floorplan_table.php from the server for security.</p>";

$conn->close();
?>









// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Database migration: Adding a table for Floor Plan...</h2>";

// Create the floorplan_settings table
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
    echo "<p style='color:green'>✓ Table 'floorplan_settings' successfully created.</p>";
} else {
    echo "<p style='color:orange'>Table 'floorplan_settings' already exists or an error occurred: " . $conn->error . "</p>";
}

// Add the fields hero_image_url and hero2_image_url to content_settings if they are not there
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero2_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero2_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

// Insert initial data if the table is empty
echo "<p>Checking initial data...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Multifunctional spaces & small cinema";
    $default_loft_desc = "Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Initial data for floorplan added</p>";
        } else {
            echo "<p style='color:red'>Error adding data: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Error preparing request: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Initial data for floorplan already exist</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Migration complete!</h1>";
echo "<p><strong>Important:</strong> Delete the file add_floorplan_table.php from the server for security.</p>";

$conn->close();
?>




// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Database migration: Adding a table for Floor Plan...</h2>";

// Create the floorplan_settings table
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
    echo "<p style='color:green'>✓ Table 'floorplan_settings' successfully created.</p>";
} else {
    echo "<p style='color:orange'>Table 'floorplan_settings' already exists or an error occurred: " . $conn->error . "</p>";
}

// Add the fields hero_image_url and hero2_image_url to content_settings if they are not there
$sql_add_hero_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

$sql_add_hero2_image = "ALTER TABLE content_settings ADD COLUMN IF NOT EXISTS hero2_image_url VARCHAR(255) DEFAULT ''";
if ($conn->query($sql_add_hero2_image) === TRUE) {
    echo "<p style='color:green'>✓ Field 'hero2_image_url' successfully added to content_settings.</p>";
} else {
    echo "<p style='color:orange'>Field 'hero2_image_url' already exists or an error occurred: " . $conn->error . "</p>";
}

// Insert initial data if the table is empty
echo "<p>Checking initial data...</p>";
$check = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
if ($check && $check->num_rows == 0) {
    $default_basement_sub = "Private floor with a separate entrance.";
    $default_basement_desc = "A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.";
    $default_basement_img = "assets/plan.jpg";
    $default_ground_sub = "Open space with a separate entrance.";
    $default_ground_desc = "A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.";
    $default_ground_queen = "assets/plan-first-queen.jpg";
    $default_ground_twin = "assets/plan-first-twin.jpg";
    $default_loft_sub = "Multifunctional spaces & small cinema";
    $default_loft_desc = "Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.";
    $default_loft_img = "assets/plan-loft.jpg";
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (id, basement_subtitle, basement_description, basement_image_url, ground_subtitle, ground_description, ground_queen_image, ground_twin_image, loft_subtitle, loft_description, loft_image_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssssss", $default_basement_sub, $default_basement_desc, $default_basement_img, $default_ground_sub, $default_ground_desc, $default_ground_queen, $default_ground_twin, $default_loft_sub, $default_loft_desc, $default_loft_img);
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Initial data for floorplan added</p>";
        } else {
            echo "<p style='color:red'>Error adding data: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Error preparing request: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>Initial data for floorplan already exist</p>";
}

echo "<hr>";
echo "<h1 style='color:green'>Migration complete!</h1>";
echo "<p><strong>Important:</strong> Delete the file add_floorplan_table.php from the server for security.</p>";

$conn->close();
?>










