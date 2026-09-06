$logs = DB::table('activity_logs')
    ->where('model', 'like', '%CompanyUser%')
    ->where(function($q) {
        $q->where('action', 'delete')->orWhere('action', 'deleted')->orWhere('action', 'حذف');
    })
    ->get();

foreach($logs as $log) {
    if (strpos($log->old_values, '"user_id":6') !== false || strpos($log->data_old, '"user_id":6') !== false || strpos($log->old_values, '"user_id": 6') !== false) {
        echo "Match found!\n";
        echo "Deleted At: " . $log->created_at . "\n";
        echo "Deleted By User ID: " . $log->created_by . "\n";
        echo "Action: " . $log->action . "\n";
        echo "Old Data: " . $log->old_values . "\n";
    }
}
echo "Total deleted company users: " . $logs->count() . "\n";
