<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-school module entitlement (Volume 1 §5.3, Book A CORE-02 §2).
 * Guarded on every route by `EnsureModuleEnabled`. BR-CORE-02-012: disabled
 * by default — a module must be explicitly turned on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('module_code', 20);
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'module_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_modules');
    }
};
