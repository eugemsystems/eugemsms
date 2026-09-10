<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2/§4. Single-use; reuse of an already-used token is a
 * theft signal (BR-CORE-05-009) — `used_at` plus `replaced_by_id` form
 * the rotation chain `ACT-RefreshApiToken` walks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('device_id', 120);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('refresh_tokens')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
