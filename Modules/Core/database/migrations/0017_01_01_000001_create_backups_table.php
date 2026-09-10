<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-13 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('type', 20);
            $table->string('scope', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('disk', 30);
            $table->string('path', 500);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->boolean('is_encrypted')->default(true);
            $table->string('status', 20);
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('triggered_by', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['type', 'status', 'completed_at']);
            $table->index(['scope', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
