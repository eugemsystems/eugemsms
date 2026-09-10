<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §2/BR-COM-06-003/004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('priority', 20);
            $table->string('audience_scope', 20);
            $table->unsignedBigInteger('audience_scope_id')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('publish_at');
            $table->timestamp('expires_at')->nullable();
            $table->json('attachment_file_ids')->nullable();
            $table->foreignId('posted_by')->constrained('users');
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'status', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
