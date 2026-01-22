<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = ['products', 'product_variants', 'stocks'];
foreach ($tables as $table) {
    echo "\n--- Table: $table ---\n";
    $columns = DB::select("DESCRIBE $table");
    foreach ($columns as $column) {
        echo sprintf("%-25s | %-20s | %-10s\n", $column->Field, $column->Type, $column->Null);
    }
}
echo "\nDONE\n";
