<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-04 §2. Append-only, same discipline as this codebase's
 * other delivery-attempt logs (e.g. `messaging_gateway_webhooks`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('webhook_subscriptions');
            $table->string('event_name', 80);
            $table->json('payload');
            $table->smallInteger('attempt_count')->default(0);
            $table->string('status', 20);
            $table->smallInteger('response_status')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
