<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-04 §2/BR-CORE-04-009: `key` is immutable after creation and
 * unique per (school, entity_type).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 60);
            $table->string('key', 60);
            $table->string('label', 150);
            $table->string('description', 255)->nullable();
            $table->string('data_type', 20);
            $table->json('options')->nullable();
            $table->string('validation_rules', 255)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_exposed_in_api')->default(true);
            $table->boolean('is_printable')->default(false);
            $table->json('visible_to_roles')->nullable();
            $table->string('group_label', 80)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'entity_type', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
