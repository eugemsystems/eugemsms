<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-001/002. `content` and `document_file_id`
 * are both nullable — a policy is either typed directly or uploaded as
 * a document, never both required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->string('category', 60);
            $table->string('version', 20);
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->date('effective_from');
            $table->date('review_due_on')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('board_approved_on')->nullable();
            $table->boolean('requires_acknowledgement')->default(false);
            $table->json('acknowledgement_audiences')->nullable();
            $table->string('status', 20);
            $table->foreignId('supersedes_policy_id')->nullable()->constrained('policies');
            $table->timestamps();

            $table->unique(['school_id', 'code', 'version'], 'policies_code_version_unique');
            $table->index(['school_id', 'status'], 'policies_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
