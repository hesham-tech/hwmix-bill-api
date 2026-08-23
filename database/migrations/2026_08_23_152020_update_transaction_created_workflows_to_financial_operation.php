<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_workflows')
            ->where('event_type', 'transaction.created')
            ->update(['event_type' => 'financial_operation.created']);
    }

    public function down(): void
    {
        DB::table('notification_workflows')
            ->where('event_type', 'financial_operation.created')
            ->update(['event_type' => 'transaction.created']);
    }
};
