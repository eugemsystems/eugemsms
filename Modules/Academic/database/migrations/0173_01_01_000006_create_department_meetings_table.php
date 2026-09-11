<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-11 §2/BR-ACA-11-008. Each entry in `action_items` carries
 * its own `status`, updated independently through
 * `UpdateActionItemStatusAction` without rewriting the rest of the
 * minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained();
            $table->date('meeting_date');
            $table->json('attendee_staff_ids');
            $table->text('agenda')->nullable();
            $table->text('minutes');
            $table->json('action_items')->nullable();
            $table->foreignId('chaired_by')->constrained('staff');
            $table->timestamps();

            $table->index(['school_id', 'department_id', 'meeting_date'], 'department_meetings_dept_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_meetings');
    }
};
