<?php
$db_host = '127.0.0.1';
$db_user = 'u715355537_bill_api';
$db_pass = '6!&?KbXe';
$db_name = 'u715355537_bill_api';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $row = $pdo->query("SELECT id, message, payload FROM error_reports ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "LATEST_ERROR_ID: " . $row['id'] . "\n";
        echo "LATEST_ERROR_MESSAGE: " . $row['message'] . "\n";
        echo "LATEST_ERROR_PAYLOAD: " . substr($row['payload'], 0, 1000) . "\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}