<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-04 §2/BR-INT-04-004/005. `signing_secret` is encrypted at
 * the model attribute level (`Illuminate\Database\Eloquent\Casts\AsEncryptedString`
 * or an equivalent cast), matching this codebase's existing pattern for
 * every other stored external credential.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('api_clients');
            $table->json('event_names');
            $table->string('target_url', 500);
            $table->text('signing_secret');
            $table->boolean('is_active')->default(true);
            $table->smallInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
