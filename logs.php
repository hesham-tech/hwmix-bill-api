$logs = DB::table('activity_logs')
    ->where(function($q) {
        $q->where('model', 'like', '%CompanyUser%')
          ->orWhere('model', 'like', '%User%');
    })
    ->where('company_id', 2)
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

foreach($logs as $log) {
    if (strpos($log->data_old, '"user_id":6') !== false || strpos($log->data_new, '"user_id":6') !== false || strpos($log->old_values, '"user_id":6') !== false || strpos($log->new_values, '"user_id":6') !== false || $log->row_id == 6) {
        echo "Date: " . $log->created_at . " | Action: " . $log->action . " | Model: " . $log->model . "\n";
        echo "Data Old: " . $log->data_old . "\n";
        echo "Data New: " . $log->data_new . "\n";
        echo "Old Vals: " . $log->old_values . "\n";
        echo "New Vals: " . $log->new_values . "\n------------------\n";
    }
}
