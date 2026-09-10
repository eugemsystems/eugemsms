<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-04 §2/BR-INT-04-006.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_provisioning_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('domain', 120);
            $table->text('credentials');
            $table->boolean('auto_provision_staff')->default(false);
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_provisioning_configs');
    }
};
