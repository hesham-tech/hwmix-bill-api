<?php
foreach (Illuminate\Support\Facades\DB::select('DESCRIBE products') as $c) {
    echo $c->Field . " | " . $c->Type . " | " . $c->Null . "\n";
}
