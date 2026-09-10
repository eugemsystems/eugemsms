<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §3. `taxpayer_tin`/`vat_number`/`certificate_pem` are
 * encrypted at the model layer (`encrypted` cast) — never plaintext
 * at rest, per BR-FIN-13-015.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_devices', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 40);
            $table->string('device_serial', 60);
            $table->string('device_branch_id', 40)->nullable();
            $table->string('taxpayer_name', 200);
            $table->text('taxpayer_tin');
            $table->text('vat_number')->nullable();
            $table->timestamp('csr_generated_at')->nullable();
            $table->text('certificate_pem')->nullable();
            $table->string('private_key_ref', 200)->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
            $table->timestamp('certificate_expires_at')->nullable();
            $table->string('environment', 20);
            $table->string('api_base_url', 255);
            $table->string('operating_mode', 20)->nullable();
            $table->smallInteger('taxpayer_day_max_hours')->nullable();
            $table->json('applicable_taxes')->nullable();
            $table->timestamp('last_config_sync_at')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->string('last_ping_status', 20)->nullable();
            $table->string('status', 20);
            $table->boolean('is_active')->default(false);

            $table->unique(['school_id', 'device_id'], 'fiscal_devices_school_device_unique');
            $table->index(['school_id', 'is_active'], 'fiscal_devices_school_active_idx');
            $table->index('certificate_expires_at', 'fiscal_devices_cert_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_devices');
    }
};
