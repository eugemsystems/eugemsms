<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/§4. `school_id` nullable — a system default rate
 * card a school falls back to when it has not configured its own,
 * mirroring `zimsec_validation_rules`' own school-scoped-or-system
 * shape (Book H3 CMP-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_rate_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('gateway_id')->constrained('message_gateways');
            $table->string('destination_prefix', 10);
            $table->bigInteger('rate_per_segment_minor');
            $table->bigInteger('whatsapp_utility_rate_minor')->nullable();
            $table->bigInteger('whatsapp_marketing_rate_minor')->nullable();
            $table->char('currency', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['gateway_id', 'destination_prefix', 'effective_from'], 'provider_rate_cards_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_rate_cards');
    }
};
