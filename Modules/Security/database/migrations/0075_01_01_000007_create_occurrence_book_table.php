<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2 ⭐/BR-OPS-06-003/012 — APPEND-ONLY, gapless
 * `entry_number` per school. No UPDATE, no DELETE — a correction is a
 * new entry referencing the original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrence_book', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('entry_number');
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->string('shift', 20)->nullable();
            $table->string('category', 40);
            $table->text('description');
            $table->string('location', 150)->nullable();
            $table->string('persons_involved', 500)->nullable();
            $table->text('action_taken')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users');
            $table->string('cctv_reference', 120)->nullable();
            $table->json('photo_file_ids')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('corrects_entry_id')->nullable()->constrained('occurrence_book');

            $table->unique(['school_id', 'entry_number'], 'occurrence_book_school_entry_unique');
            $table->index(['school_id', 'occurred_at'], 'occurrence_book_occurred_idx');
            $table->index(['school_id', 'category', 'occurred_at'], 'occurrence_book_category_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrence_book');
    }
};
