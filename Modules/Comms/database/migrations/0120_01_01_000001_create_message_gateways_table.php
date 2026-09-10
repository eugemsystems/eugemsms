<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/BR-COM-01-001/002. `credentials` is encrypted at
 * the model layer (never stored plain, never logged). `priority`
 * (lowest first) is the failover order within a channel —
 * `NotificationChannelDriverRegistry::forChannel()` resolves a driver
 * per CHANNEL (Book A's own contract shape), so this module's drivers
 * resolve which gateway to actually use internally, by school +
 * channel + priority + health.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_gateways', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('driver', 30);
            $table->string('name', 120);
            $table->text('credentials');
            $table->text('webhook_secret')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_sandbox')->default(false);
            $table->smallInteger('priority')->default(0);
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('health_status', 20)->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'channel', 'driver'], 'message_gateways_channel_driver_unique');
            $table->index(['school_id', 'channel', 'is_active', 'priority'], 'message_gateways_failover_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_gateways');
    }
};
