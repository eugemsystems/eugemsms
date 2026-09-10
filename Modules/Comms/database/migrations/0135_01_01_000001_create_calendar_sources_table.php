<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §2/BR-COM-06-001. A materialization of
 * `Modules\Comms\Domain\Registry\CalendarSourceRegistry` — same
 * code-owns-the-list, DB-is-a-queryable-mirror pattern as
 * `dashboard_widgets`/`WidgetRegistry`. No `school_id`: a registered
 * source (e.g. "CORE-03 owns term_dates") is a system-wide fact, not a
 * per-school one — the per-school data it produces lives in
 * `calendar_events`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('module_code', 20);
            $table->string('source_type', 40);
            $table->char('default_colour', 7)->nullable();
            $table->string('default_audience_scope', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module_code', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sources');
    }
};
