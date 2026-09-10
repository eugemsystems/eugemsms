<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-03 §2/BR-COM-03-001/003/004. A materialization of
 * `Modules\Comms\Domain\Registry\WidgetRegistry` — mirrors
 * `SettingDefinitionRegistry::syncToDatabase()`'s own pattern (code
 * owns the list; this table exists so `school_widget_settings` has a
 * real row to reference and so the config screen can query without
 * touching the registry directly). `data_endpoint` is informational
 * (what a client-side app would call) — the ACTUAL resolution is a
 * PHP closure on the registry entry, never re-derived from this string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('module_code', 20);
            $table->string('persona', 20);
            $table->string('title', 120);
            $table->string('data_endpoint', 200);
            $table->smallInteger('min_grade_ordinal')->nullable();
            $table->string('requires_module', 20)->nullable();
            $table->boolean('default_enabled')->default(true);
            $table->smallInteger('default_sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
