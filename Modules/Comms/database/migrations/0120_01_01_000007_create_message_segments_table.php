<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/§4 ⭐/BR-COM-01-009. One row per SMS send, recording
 * the ACTUAL encoding and segment count used — after normalisation,
 * never estimated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained('notifications');
            $table->string('encoding', 10);
            $table->smallInteger('character_count');
            $table->smallInteger('segment_count');
            $table->foreignId('rate_card_id')->nullable()->constrained('provider_rate_cards');
            $table->bigInteger('cost_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'notification_id'], 'message_segments_notification_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_segments');
    }
};
