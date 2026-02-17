<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->unsignedBigInteger('created_by')->nullable()->change();
            if (!Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('categories', 'synonyms')) {
                $table->json('synonyms')->nullable()->after('slug');
            }
        });

        // Populate existing slugs if any were blank/null
        \DB::table('categories')->whereNull('slug')->orWhere('slug', '')->orderBy('id')->each(function ($cat) {
            \DB::table('categories')->where('id', $cat->id)->update([
                'slug' => \Illuminate\Support\Str::slug($cat->name) . '-' . $cat->id
            ]);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->unique()->change();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->unsignedBigInteger('created_by')->nullable()->change();
            if (!Schema::hasColumn('brands', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('brands', 'synonyms')) {
                $table->json('synonyms')->nullable()->after('slug');
            }
        });

        // Populate existing slugs
        \DB::table('brands')->whereNull('slug')->orWhere('slug', '')->orderBy('id')->each(function ($brand) {
            \DB::table('brands')->where('id', $brand->id)->update([
                'slug' => \Illuminate\Support\Str::slug($brand->name) . '-' . $brand->id
            ]);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('slug')->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->dropColumn(['slug', 'synonyms']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->dropColumn(['slug', 'synonyms']);
        });
    }
};
