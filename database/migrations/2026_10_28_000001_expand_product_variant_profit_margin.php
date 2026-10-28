<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('profit_margin', 65, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('product_variants')
            ->where('profit_margin', '>', 999.99)->orWhere('profit_margin', '<', -999.99)->exists()) {
            throw new \RuntimeException('Cannot narrow profit_margin while values exceed the original range.');
        }

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('profit_margin', 5, 2)->nullable()->change();
        });
    }
};
