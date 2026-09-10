<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resumability (BR-CORE-01-003): a resumed installation restarts at the
 * first non-completed step, never step 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_steps', function (Blueprint $table): void {
            $table->id();
            $table->string('step_key', 50)->unique();
            $table->string('status', 20)->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('install_steps');
    }
};
