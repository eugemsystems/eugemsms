<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-08 §2/BR-ACA-08-010. `hidden_reason`/`hidden_at` are this
 * migration's own addition — the spec's literal table lists only
 * `is_hidden`/`hidden_by`, but BR-ACA-08-010 explicitly requires
 * "hiding requires a reason logged against the moderator", which needs
 * somewhere to store that reason (deviation noted per this project's
 * convention of documenting spec extensions inline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('discussion_threads')->cascadeOnDelete();
            $table->string('posted_by_type', 20);
            $table->unsignedBigInteger('posted_by_id');
            $table->text('content');
            $table->boolean('is_hidden')->default(false);
            $table->foreignId('hidden_by')->nullable()->constrained('users');
            $table->text('hidden_reason')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['school_id', 'thread_id'], 'discussion_posts_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_posts');
    }
};
