<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2. Append-only — BR-CORE-05-007: every login attempt,
 * successful or not, is written here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 150);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guard', 20);
            $table->boolean('was_successful');
            $table->string('failure_reason', 60)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('attempted_at');

            $table->index(['identifier', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
