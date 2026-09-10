<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/BR-COM-01-005/006 ⭐. `notification_key` is
 * genuinely nullable and NOT what `WhatsAppGatewayDriver::send()`
 * matches on — see that driver's own docblock for why: Book A's real
 * `NotificationChannelDriver::send()` contract takes only
 * `(address, subject, body)`, with no notification key, so the driver
 * cannot look up a template by key. It selects the school's single
 * active `approved` template instead — the honest, buildable
 * interpretation of "an approved template" given that constraint.
 * `notification_key` stays on the row for administrators tagging a
 * template's intended purpose, and for a future pass where the
 * `NotificationChannelDriver` contract itself grows the key through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('waba_id')->constrained('whatsapp_business_accounts');
            $table->string('notification_key', 80)->nullable();
            $table->string('meta_template_name', 120);
            $table->string('category', 20);
            $table->string('language', 10);
            $table->string('header_type', 20)->nullable();
            $table->text('body_text');
            $table->string('footer_text', 120)->nullable();
            $table->json('buttons')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('review_status', 20);
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('meta_template_id', 60)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'meta_template_name', 'language'], 'whatsapp_templates_name_lang_unique');
            $table->index(['school_id', 'review_status'], 'whatsapp_templates_review_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
