<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/§3 ⭐⭐/BR-BRD-08-003 — per-case, per-person. Named
 * reason, time-boxed by default, individually revocable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('safeguarding_cases');
            $table->foreignId('user_id')->constrained();
            $table->string('access_level', 20);
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('granted_at');
            $table->string('reason', 255);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->string('revocation_reason', 255)->nullable();

            $table->index(['case_id', 'user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_access_grants');
    }
};
