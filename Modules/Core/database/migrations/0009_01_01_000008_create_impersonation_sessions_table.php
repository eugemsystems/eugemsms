<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2. Append-only — every action taken during
 * impersonation is separately audited as
 * `performed_by = impersonated, on_behalf_of = impersonator`
 * (BR-CORE-05-019); this table only tracks the session envelope itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('impersonator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('impersonated_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->string('ticket_reference', 60)->nullable();
            $table->string('consent_reference', 120)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->json('actions_performed')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
    }
};
