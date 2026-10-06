<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §6 / Book J SAA-02 BR-SAA-02-002. Vendor impersonation is consent-gated by a
 * structured, time-boxed, revocable grant issued by the customer's own administrator for a named
 * support ticket — not a free-text "consent reference" the vendor types in. Every vendor
 * impersonation session records the grant that authorised it and whether it is read-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_reference', 60);
            $table->text('reason');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'expires_at']);
        });

        Schema::table('impersonation_sessions', function (Blueprint $table): void {
            $table->foreignId('access_grant_id')->nullable()->constrained('support_access_grants')->nullOnDelete();
            $table->boolean('is_read_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('impersonation_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('access_grant_id');
            $table->dropColumn('is_read_only');
        });

        Schema::dropIfExists('support_access_grants');
    }
};
