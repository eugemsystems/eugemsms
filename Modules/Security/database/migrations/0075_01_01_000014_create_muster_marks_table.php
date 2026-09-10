<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §3 ⭐⭐/BR-OPS-06-008/009 — NOT in the spec's own §2
 * data model. `emergency_drills` carries only aggregate
 * `mustered_headcount`/`unaccounted_count` columns; nothing tracks
 * WHICH individual person a marshal tapped present, which is exactly
 * what "the unaccounted list updates live" and "sick bay occupants
 * appear first with an assistance flag" both need per-person state
 * for. `person_type`/`person_id` is a plain polymorphic pair rather
 * than five separate nullable FKs, since the five source types
 * (student/staff/visitor/contractor_worker) live in four different
 * modules and a real FK to each would make this table depend on all
 * of them for a single column each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muster_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drill_id')->constrained('emergency_drills');
            $table->string('person_type', 20);
            $table->unsignedBigInteger('person_id');
            $table->string('assembly_point', 100)->nullable();
            $table->timestamp('marked_present_at');
            $table->foreignId('marked_by')->constrained('users');

            $table->unique(['drill_id', 'person_type', 'person_id'], 'muster_marks_drill_person_unique');
            $table->index(['drill_id'], 'muster_marks_drill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muster_marks');
    }
};
