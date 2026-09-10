<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-01 §2. Not tenant-scoped — this table describes the
 * platform installation itself, which exists before any school does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_installations', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('installed_at');
            $table->string('installed_version', 20);
            $table->string('deployment_mode', 20);
            $table->string('licence_key')->nullable();
            $table->timestamp('licence_activated_at')->nullable();
            $table->timestamp('licence_expires_at')->nullable();
            $table->char('installation_uuid', 36)->unique();
            $table->string('server_fingerprint', 128)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_installations');
    }
};
