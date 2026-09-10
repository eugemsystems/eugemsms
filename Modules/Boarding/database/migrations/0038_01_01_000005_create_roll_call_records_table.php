<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/§4/BR-BRD-02-003/004. `is_auto_populated` +
 * `source_reference` let the housemaster see *why* a learner is
 * marked accounted for, and override with a mandatory note — the
 * override itself is just a normal update to this row (`marked_by`
 * changes to the housemaster, `note` records the reason), not a
 * separate table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roll_call_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roll_call_id')->constrained('roll_calls');
            $table->foreignId('student_id')->constrained();
            $table->date('roll_date');
            $table->string('status', 20);
            $table->boolean('is_auto_populated')->default(false);
            $table->string('source_reference', 120)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->timestamp('marked_at');
            $table->string('device_source', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->text('resolution_note')->nullable();

            $table->unique(['roll_call_id', 'student_id'], 'roll_call_records_unique');
            $table->index(['school_id', 'student_id', 'roll_date'], 'roll_call_records_student_idx');
            $table->index(['school_id', 'status', 'roll_date'], 'roll_call_records_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_call_records');
    }
};
