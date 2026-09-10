<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/BR-COM-01-008. Per-network alphanumeric SMS sender
 * ID registration status — a school registers separately per network
 * in Zimbabwe (Econet, NetOne, Telecel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_ids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gateway_id')->constrained('message_gateways');
            $table->string('sender_id', 20);
            $table->string('network', 20)->nullable();
            $table->string('registration_reference', 80)->nullable();
            $table->string('status', 20);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'gateway_id', 'sender_id', 'network'], 'sender_ids_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_ids');
    }
};
