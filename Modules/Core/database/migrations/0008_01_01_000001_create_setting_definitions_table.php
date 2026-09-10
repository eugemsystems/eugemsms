<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-04 §2. Global, not tenant data — "registered in code, synced
 * on deploy", the same code-owns-the-list / table-mirrors-it split as
 * `rollover_handlers` (CORE-03) and `SeedPackRegistry` (CORE-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('module_code', 20);
            $table->string('group_key', 60);
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->string('data_type', 20);
            $table->text('default_value')->nullable();
            $table->string('validation_rules', 255)->nullable();
            $table->json('options')->nullable();
            $table->string('ui_control', 30);
            $table->string('lowest_scope', 20);
            $table->boolean('is_encrypted')->default(false);
            $table->string('is_locked_on_tier', 20)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_definitions');
    }
};
