<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-001/004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_key', 60)->unique();
            $table->string('module_code', 20);
            $table->string('base_model_class', 255);
            $table->boolean('default_school_scoped')->default(true);
            $table->json('allowed_join_entity_keys')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_entities');
    }
};
