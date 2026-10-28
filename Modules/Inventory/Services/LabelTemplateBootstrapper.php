<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LabelTemplateBootstrapper
{
    public static function ensure(int $companyId): void
    {
        if (! Schema::hasTable('label_templates') || ! Schema::hasColumn('companies', 'default_label_template_id')) {
            return;
        }
        DB::transaction(function () use ($companyId) {
            $company = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            if (! $company || DB::table('label_templates')->where('company_id', $companyId)->exists()) {
                return;
            }
            $now = now();
            $id = DB::table('label_templates')->insertGetId(['company_id' => $companyId, 'name' => 'القالب القياسي — 50 × 60 مم', 'archived' => false, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $revision = DB::table('label_template_revisions')->insertGetId(['label_template_id' => $id, 'version' => 1, 'design' => json_encode(LabelTemplateDesign::starter(), JSON_UNESCAPED_UNICODE), 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('label_templates')->where('id', $id)->update(['published_revision_id' => $revision]);
            if (! $company->default_label_template_id) {
                DB::table('companies')->where('id', $companyId)->update(['default_label_template_id' => $id]);
            }
        });
    }
}
