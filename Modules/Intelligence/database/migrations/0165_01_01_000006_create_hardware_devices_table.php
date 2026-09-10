<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-04 §2/§3 ⭐/BR-INT-04-007/008/009. The registry every
 * `device_source` column elsewhere in the specification implies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hardware_devices', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('device_type', 30);
            $table->string('location', 150)->nullable();
            $table->string('purpose', 40)->nullable();
            $table->foreignId('api_client_id')->constrained('api_clients');
            $table->string('firmware_version', 30)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('status', 20)->default('offline');
            $table->timestamps();

            $table->index(['school_id', 'device_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_devices');
    }
};
