<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-07 §2/BR-CORE-07-010. Append-only — nothing here is ever
 * edited or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->smallInteger('step_number');
            $table->string('action', 20);
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('on_behalf_of_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('acted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
    }
};
