$tables = DB::select('SHOW TABLES');
foreach($tables as $table) {
    $vars = get_object_vars($table);
    $tableName = array_values($vars)[0];
    if (strpos($tableName, 'log') !== false || strpos($tableName, 'audit') !== false) {
        echo $tableName . "\n";
    }
}
