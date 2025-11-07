<?php
// Floor plan API handler

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_floorplan') {
    try {
        // Check if table exists first
        $tableCheck = $conn->query("SHOW TABLES LIKE 'floorplan_settings'");
        if ($tableCheck->num_rows === 0) {
            // Table doesn't exist, return default values
            $defaultData = [
                'basement_subtitle' => 'Private floor with a separate entrance.',
                'basement_description' => 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.',
                'basement_image_url' => 'assets/plan.jpg',
                'ground_subtitle' => 'Open space with a separate entrance.',
                'ground_description' => 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.',
                'ground_queen_image' => 'assets/plan.jpg',
                'ground_image_url' => 'assets/plan.jpg', // Universal field name
                'loft_subtitle' => 'Private top-floor space under the roof.',
                'loft_description' => 'A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.',
                'loft_image_url' => 'assets/plan.jpg'
            ];
            sendSuccess($defaultData);
            exit;
        }
        
        $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
        
        if (!$result) {
            sendError('Query failed: ' . $conn->error);
        }
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            // Universal: add ground_image_url as alias to ground_queen_image for unified format
            if (isset($data['ground_queen_image']) && !isset($data['ground_image_url'])) {
                $data['ground_image_url'] = $data['ground_queen_image'];
            }
            sendSuccess($data);
        } else {
            // Return default values if no data exists
            $defaultData = [
                'basement_subtitle' => 'Private floor with a separate entrance.',
                'basement_description' => 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.',
                'basement_image_url' => 'assets/plan.jpg',
                'ground_subtitle' => 'Open space with a separate entrance.',
                'ground_description' => 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.',
                'ground_queen_image' => 'assets/plan.jpg',
                'ground_image_url' => 'assets/plan.jpg', // Universal field name
                'loft_subtitle' => 'Private top-floor space under the roof.',
                'loft_description' => 'A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.',
                'loft_image_url' => 'assets/plan.jpg'
            ];
            sendSuccess($defaultData);
        }
    } catch (Exception $e) {
        sendError('Database error: ' . $e->getMessage());
    }
    exit;
}
?>
