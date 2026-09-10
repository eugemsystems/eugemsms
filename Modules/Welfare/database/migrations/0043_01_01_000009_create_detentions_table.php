<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/BR-BRD-07-011/012 ⭐ — closes the `detention`
 * roll-status stub `BRD-02` §4 left open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('sanction_id')->nullable()->constrained('sanctions');
            $table->foreignId('student_id')->constrained();
            $table->date('scheduled_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('venue', 120)->nullable();
            $table->foreignId('supervisor_staff_id')->nullable()->constrained('staff');
            $table->text('task_set')->nullable();
            $table->boolean('attended')->nullable();
            $table->string('attendance_note', 255)->nullable();
            $table->string('status', 20);

            $table->index(['school_id', 'scheduled_date', 'status']);
            $table->index(['school_id', 'student_id', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detentions');
    }
};
