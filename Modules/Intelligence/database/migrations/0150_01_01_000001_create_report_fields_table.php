<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-001/002/003. The field registry every
 * module contributes to — a materialization of
 * `Modules\Intelligence\Domain\Registry\ReportFieldRegistry`, same
 * code-owns-the-list pattern already established repeatedly this
 * book set (`WidgetRegistry`, `CalendarSourceRegistry`, etc.). No
 * `school_id`: a registered field is a system-wide fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('module_code', 20);
            $table->string('entity_key', 60);
            $table->string('field_key', 80);
            $table->string('label', 150);
            $table->string('data_type', 20);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_groupable')->default(true);
            $table->boolean('is_aggregatable')->default(false);
            $table->string('required_permission', 120);
            $table->boolean('is_sensitive')->default(false);
            $table->json('enum_options')->nullable();
            $table->timestamps();

            $table->unique(['module_code', 'entity_key', 'field_key']);
            $table->index(['entity_key', 'is_filterable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_fields');
    }
};
