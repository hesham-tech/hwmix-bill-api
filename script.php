$invoicesCount = DB::table('invoices')->where('company_id', 2)->where('user_id', 6)->count();
echo "Invoices for User 6 in Company 2: " . $invoicesCount . "\n";

$hasBusinessRelation = DB::table('business_relations')->where('company_id', 2)->where('user_id', 6)->exists();
echo "Business Relation Exists: " . ($hasBusinessRelation ? 'Yes' : 'No') . "\n";

if (Schema::hasColumn('company_user', 'deleted_at')) {
    $trashedCount = DB::table('company_user')->where('company_id', 2)->where('user_id', 6)->whereNotNull('deleted_at')->count();
    echo "Trashed company_user records: " . $trashedCount . "\n";
} else {
    echo "No deleted_at column in company_user\n";
}

$logs = DB::table('activity_log')
    ->where('properties', 'like', '%"user_id":6%')
    ->orWhere('properties', 'like', '%"user_id": 6%')
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

echo "Found " . $logs->count() . " recent logs involving user_id 6 in properties.\n";
foreach($logs as $log) {
    if (strpos($log->properties, '"company_id":2') !== false || strpos($log->properties, '"company_id": 2') !== false || strpos($log->properties, '"company_id":"2"') !== false || $log->subject_type === 'App\Models\CompanyUser') {
        echo "Date: " . $log->created_at . " | Event: " . $log->event . " | Subject: " . $log->subject_type . "\n";
        echo "Props: " . $log->properties . "\n------------------\n";
    }
}
