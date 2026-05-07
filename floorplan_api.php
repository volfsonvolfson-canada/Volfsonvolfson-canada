<?php
// Floor plan API handler

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_floorplan') {
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'floorplan_settings'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            sendSuccess(function_exists('btb_floorplan_api_payload_from_row') ? btb_floorplan_api_payload_from_row([]) : []);
            exit;
        }

        $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");

        if (!$result) {
            sendError('Query failed: ' . $conn->error);
        }

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            sendSuccess(function_exists('btb_floorplan_api_payload_from_row') ? btb_floorplan_api_payload_from_row(is_array($data) ? $data : []) : $data);
        } else {
            sendSuccess(function_exists('btb_floorplan_api_payload_from_row') ? btb_floorplan_api_payload_from_row([]) : []);
        }
    } catch (Exception $e) {
        sendError('Database error: ' . $e->getMessage());
    }
    exit;
}
?>
