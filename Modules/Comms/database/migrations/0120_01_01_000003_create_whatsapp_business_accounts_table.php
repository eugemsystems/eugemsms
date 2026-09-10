<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/BR-COM-01-007 ⭐. `quality_rating` dropping to `red`
 * pauses non-critical sends on the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_business_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gateway_id')->constrained('message_gateways');
            $table->string('waba_id', 60);
            $table->string('display_phone_number', 30);
            $table->string('display_name', 120);
            $table->string('display_name_status', 20)->nullable();
            $table->string('quality_rating', 20)->nullable();
            $table->string('messaging_limit_tier', 30)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['school_id', 'waba_id'], 'whatsapp_business_accounts_waba_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_business_accounts');
    }
};
