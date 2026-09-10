<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/BR-BRD-07-008/009.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appeals', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sanction_id')->constrained('sanctions');
            $table->foreignId('lodged_by_guardian_id')->nullable()->constrained('guardians');
            $table->boolean('lodged_by_student')->default(false);
            $table->text('grounds');
            $table->unsignedBigInteger('supporting_document_id')->nullable();
            $table->timestamp('lodged_at');
            $table->timestamp('heard_at')->nullable();
            $table->json('heard_by')->nullable();
            $table->string('outcome', 30)->nullable();
            $table->text('outcome_reason')->nullable();
            $table->foreignId('new_sanction_id')->nullable()->constrained('sanctions');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->string('status', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
