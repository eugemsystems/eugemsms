<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('notification_key', 80);
            $table->string('recipient_type', 60);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_address', 200);
            $table->string('channel', 20);
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->json('context')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('provider', 40)->nullable();
            $table->string('provider_message_id', 120)->nullable();
            $table->tinyInteger('attempt_count')->unsigned()->default(0);
            $table->bigInteger('cost_minor')->nullable();
            $table->char('cost_currency', 3)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('dedupe_key', 120)->nullable();
            $table->timestamp('created_at');

            $table->index(['school_id', 'status', 'created_at']);
            $table->index(['recipient_type', 'recipient_id', 'created_at']);
            $table->unique(['school_id', 'dedupe_key'], 'notifications_school_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
