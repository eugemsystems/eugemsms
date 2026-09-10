<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-03 §2/BR-COM-03-005/006/007. `device_id` is the SAME
 * free-form client-generated identifier Book A CORE-05's own
 * `personal_access_tokens.device_id` groups tokens by — this table
 * doesn't duplicate CORE-05's session/token concept, it adds the
 * push-registration and app-lock concerns CORE-05 never carried,
 * keyed the same way so `RevokePortalDeviceAction` can revoke both in
 * one call. `app_pin_hash` is deliberately separate from the
 * account's own password hash (BR-COM-03-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_devices', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 120);
            $table->string('platform', 20);
            $table->string('push_token', 255)->nullable();
            $table->timestamp('push_token_updated_at')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('requires_biometric_lock')->default(false);
            $table->string('app_pin_hash', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id'], 'portal_devices_user_device_unique');
            $table->index('push_token', 'portal_devices_push_token_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_devices');
    }
};
