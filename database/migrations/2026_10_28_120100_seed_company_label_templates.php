<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\LabelTemplateBootstrapper;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->select('id')->orderBy('id')->chunkById(100, function ($companies) {
            foreach ($companies as $company) {
                LabelTemplateBootstrapper::ensure($company->id);
            }
        });
    }

    public function down(): void
    {
        // Never erase company-authored templates when rolling back the seed step.
    }
};
