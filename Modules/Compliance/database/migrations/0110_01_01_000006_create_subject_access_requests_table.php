<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-008 ⭐ (AC-CMP-03-002/003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('request_type', 30);
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('requester_name', 200);
            $table->string('requester_relationship', 60)->nullable();
            $table->boolean('identity_verified')->default(false);
            $table->string('verification_method', 60)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('received_at');
            $table->date('due_by');
            $table->text('scope_description');
            $table->string('status', 20);
            $table->text('refusal_grounds')->nullable();
            $table->unsignedBigInteger('response_file_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'status', 'due_by'], 'subject_access_requests_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_access_requests');
    }
};
