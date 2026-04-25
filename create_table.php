<?php
// Create floorplan_settings table if it doesn't exist
require_once 'common.php';

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'floorplan_settings'");
    
    if ($tableCheck->num_rows === 0) {
        echo "Creating floorplan_settings table...\n";
        
        $createTable = "CREATE TABLE floorplan_settings (
            id INT PRIMARY KEY,
            basement_subtitle TEXT,
            basement_description TEXT,
            basement_image_url VARCHAR(255),
            ground_subtitle TEXT,
            ground_description TEXT,
            ground_queen_image VARCHAR(255),
            ground_twin_image VARCHAR(255),
            loft_subtitle TEXT,
            loft_description TEXT,
            loft_image_url VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($createTable)) {
            echo "✅ Table created successfully\n";
            
            // Insert default record
            $insertDefault = "INSERT INTO floorplan_settings (
                id, basement_subtitle, basement_description, basement_image_url,
                ground_subtitle, ground_description, ground_queen_image, ground_twin_image,
                loft_subtitle, loft_description, loft_image_url
            ) VALUES (
                1, 
                'Private floor with a separate entrance.',
                'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.',
                'assets/plan.jpg',
                'Open space with a separate entrance.',
                'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.',
                'assets/plan.jpg',
                '',
                'Multifunctional spaces & small cinema',
                'Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.',
                'assets/plan-loft.jpg'
            )";
            
            if ($conn->query($insertDefault)) {
                echo "✅ Default data inserted successfully\n";
            } else {
                echo "❌ Failed to insert default data: " . $conn->error . "\n";
            }
        } else {
            echo "❌ Failed to create table: " . $conn->error . "\n";
        }
    } else {
        echo "✅ Table floorplan_settings already exists\n";
        
        // Check if record exists
        $recordCheck = $conn->query("SELECT id FROM floorplan_settings WHERE id = 1");
        if ($recordCheck->num_rows === 0) {
            echo "Creating default record...\n";
            
            $insertDefault = "INSERT INTO floorplan_settings (
                id, basement_subtitle, basement_description, basement_image_url,
                ground_subtitle, ground_description, ground_queen_image, ground_twin_image,
                loft_subtitle, loft_description, loft_image_url
            ) VALUES (
                1, 
                'Private floor with a separate entrance.',
                'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.',
                'assets/plan.jpg',
                'Open space with a separate entrance.',
                'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.',
                'assets/plan.jpg',
                '',
                'Multifunctional spaces & small cinema',
                'Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.',
                'assets/plan-loft.jpg'
            )";
            
            if ($conn->query($insertDefault)) {
                echo "✅ Default record created successfully\n";
            } else {
                echo "❌ Failed to create default record: " . $conn->error . "\n";
            }
        } else {
            echo "✅ Record with id=1 already exists\n";
        }
    }
    
    // Show current data
    $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo "\n📊 Current data:\n";
        echo "Basement image: " . ($data['basement_image_url'] ?: 'Not set') . "\n";
        echo "Ground image: " . ($data['ground_queen_image'] ?: 'Not set') . "\n";
        echo "Loft image: " . ($data['loft_image_url'] ?: 'Not set') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
