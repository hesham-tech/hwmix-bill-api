<?php
$db_host = '127.0.0.1';
$db_user = 'u715355537_bill_api';
$db_pass = '6!&?KbXe';
$db_name = 'u715355537_bill_api';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $row = $pdo->query("SELECT payload FROM error_reports ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $payload = json_decode($row['payload'], true);
        echo "PAYLOAD_JSON_START\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo "PAYLOAD_JSON_END\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}