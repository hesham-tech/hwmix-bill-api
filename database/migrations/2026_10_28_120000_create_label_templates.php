<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->boolean('archived')->default(false);
            $table->unsignedBigInteger('draft_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'archived']);
        });
        Schema::create('label_template_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('design');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['label_template_id', 'version']);
        });
        Schema::create('label_template_asset_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('label_template_revision_id');
            $table->foreign('label_template_revision_id', 'label_asset_revision_fk')->references('id')->on('label_template_revisions')->cascadeOnDelete();
            $table->unsignedBigInteger('media_file_id')->index();
            $table->unique(['label_template_revision_id', 'media_file_id'], 'label_revision_media_unique');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('default_label_template_id')->nullable()->constrained('label_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->dropConstrainedForeignId('default_label_template_id'));
        Schema::dropIfExists('label_template_asset_references');
        Schema::dropIfExists('label_template_revisions');
        Schema::dropIfExists('label_templates');
    }
};
