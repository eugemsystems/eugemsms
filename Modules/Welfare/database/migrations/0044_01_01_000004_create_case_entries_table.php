<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/BR-BRD-08-008 ⭐ — APPEND-ONLY chronology.
 * `content` is `SecondaryEncrypted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_entries', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('safeguarding_cases');
            $table->string('entry_type', 30);
            $table->timestamp('entry_at');
            $table->timestamp('recorded_at');
            $table->text('content');
            $table->boolean('is_learner_account')->default(false);
            $table->json('present_persons')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->json('attachment_file_ids')->nullable();

            $table->index(['case_id', 'entry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_entries');
    }
};
