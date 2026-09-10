<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-04 §2/BR-INT-04-001/002/007. Both a genuine third-party
 * integration and a hardware device's own credential are rows here —
 * `client_type` distinguishes them, but both go through the same
 * scoping and revocation machinery (BR-INT-04-007).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('client_type', 20);
            $table->string('contact_email', 150)->nullable();
            $table->string('api_key_hash', 255);
            $table->json('scoped_abilities');
            $table->integer('rate_limit_per_minute')->default(60);
            $table->json('ip_allowlist')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
