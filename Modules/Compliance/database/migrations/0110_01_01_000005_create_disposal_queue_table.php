<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-006 ⭐ (AC-CMP-03-005). A record reaching
 * its retention date lands here for review — never disposed
 * automatically where the schedule requires review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposal_queue', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('retention_schedules');
            $table->string('record_type', 60);
            $table->unsignedBigInteger('record_id');
            $table->date('eligible_on');
            $table->string('review_status', 20);
            $table->date('deferred_until')->nullable();
            $table->string('deferral_reason', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('disposed_at')->nullable();
            $table->string('disposal_method', 30)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'record_type', 'record_id'], 'disposal_queue_record_unique');
            $table->index(['school_id', 'eligible_on', 'review_status'], 'disposal_queue_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_queue');
    }
};
