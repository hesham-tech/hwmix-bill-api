<?php
$db_host = '127.0.0.1';
$db_user = 'u715355537_bill_api';
$db_pass = '6!&?KbXe';
$db_name = 'u715355537_bill_api';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT id, type, message, payload, created_at FROM error_reports WHERE created_at >= CURDATE() ORDER BY id DESC LIMIT 5");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "---RESULTS_START---" . PHP_EOL;
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo "---RESULTS_END---" . PHP_EOL;
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}