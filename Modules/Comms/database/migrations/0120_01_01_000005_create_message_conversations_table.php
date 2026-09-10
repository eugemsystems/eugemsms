<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2 ⭐/BR-COM-01-003/004. The WhatsApp 24-hour session
 * window. `session_expires_at` is `last_inbound_at + 24h`, updated on
 * every inbound message from the contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('waba_id')->constrained('whatsapp_business_accounts');
            $table->string('contact_phone', 30);
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->string('conversation_category', 20)->nullable();
            $table->foreignId('opened_by_template_id')->nullable()->constrained('whatsapp_templates');
            $table->timestamps();

            $table->unique(['school_id', 'waba_id', 'contact_phone'], 'message_conversations_contact_unique');
            $table->index(['school_id', 'session_expires_at'], 'message_conversations_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_conversations');
    }
};
