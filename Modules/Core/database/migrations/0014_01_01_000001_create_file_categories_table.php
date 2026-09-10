<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-10 §2. "Registered by modules" — code owns the list,
 * this table mirrors it, the same split as `setting_definitions`
 * (CORE-04) / `rollover_handlers` (CORE-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 120);
            $table->string('module_code', 20);
            $table->json('allowed_mimes');
            $table->bigInteger('max_size_bytes');
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('generates_variants')->default(false);
            $table->boolean('requires_expiry')->default(false);
            $table->tinyInteger('retention_years')->unsigned()->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_categories');
    }
};
