<?php
$config = config("permissions_keys");
$json = json_encode($config);
if ($json === false) {
    echo "JSON Encode Failed: " . json_last_error_msg() . "\n";
} else {
    echo "JSON Encode Success!\n";
}

