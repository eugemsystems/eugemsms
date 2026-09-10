<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-08 §2/BR-CORE-08-001..004. `school_id` is nullable —
 * some activity (platform-level, pre-tenant-resolution) has no school
 * at all — so, unlike most tenant tables, this one is NOT
 * `BelongsToSchool`: the writer always supplies `school_id` (or null)
 * explicitly rather than relying on an ambient, auto-filled context.
 *
 * `request_id` is `VARCHAR(36)`, not the spec's literal `CHAR(26)`:
 * Book A Part 1's already-shipped `RecordActivity` middleware
 * generates this id as a UUID (36 chars, `Str::uuid()`), not a ULID —
 * changing that foundational, already-relied-upon middleware for a
 * cosmetic length match here would be a much larger, riskier change
 * than widening this column to fit what it actually receives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('log_name', 60);
            $table->string('description', 255);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('event', 30)->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 36)->nullable();
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['school_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_id', 'created_at']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
