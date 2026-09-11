<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-08 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_space_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'course_space_id'], 'discussion_threads_space_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_threads');
    }
};
