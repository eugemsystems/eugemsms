<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §2/BR-COM-06-001/002. A rebuildable materialised
 * aggregate — never hand-edited, always regenerated from a registered
 * `calendar_sources` entry by `RebuildCalendarAction`. Deliberately
 * NOT `BelongsToSession` (Book A Part 1.3): the guard that trait wires
 * would refuse a rebuild touching a now-`LOCKED` past term's own
 * historical dates, which is exactly the case a rebuild must be able
 * to write (a moved examination date years later re-derives the same
 * historical row). `academic_year_id`/`term_id` are plain FKs, set by
 * the rebuild from the SOURCE record's own year/term, not by
 * `SessionContext`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_module_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('location', 200)->nullable();
            $table->string('audience_scope', 20);
            $table->unsignedBigInteger('audience_scope_id')->nullable();
            $table->char('colour', 7)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamp('rebuilt_at');
            $table->timestamps();

            $table->unique(['school_id', 'source_type', 'source_module_id']);
            $table->index(['school_id', 'starts_at', 'audience_scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
