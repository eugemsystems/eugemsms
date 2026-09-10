<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-01 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_reports', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->string('primary_entity_key', 60);
            $table->json('selected_fields');
            $table->json('filters')->nullable();
            $table->json('group_by')->nullable();
            $table->json('aggregations')->nullable();
            $table->json('sort')->nullable();
            $table->string('chart_type', 20)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'primary_entity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_reports');
    }
};
