<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-01 §2/BR-SAA-01-007 — on-premise deployments. Mirrors
 * `system_installations`' own installation-time grace concept
 * (`Modules\Core\Models\SystemInstallation`, Book A CORE-01) but as a
 * recurring, per-tenant re-validation rather than a one-off activation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained();
            $table->string('key_value', 255)->unique();
            $table->char('installation_uuid', 36)->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->integer('offline_grace_days')->default(14);
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_keys');
    }
};
