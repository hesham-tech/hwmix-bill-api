<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=u715355537_api_teste', 'u715355537_api_teste', '29Qjbd$J');
    $stmt = $db->query('SELECT id, message, type, created_at, screenshot_url FROM error_reports ORDER BY id DESC LIMIT 5');
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "--- START REPORTS ---\n";
    foreach ($results as $row) {
        echo "ID: {$row['id']} | Type: {$row['type']} | Msg: {$row['message']} | Shot: {$row['screenshot_url']} | Date: {$row['created_at']}\n";
    }
    echo "--- END REPORTS ---\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
