<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2 ⭐. WHO a structure applies to — every row must
 * match for the structure to be selected (BR-FIN-02-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structure_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->string('attribute', 50);
            $table->string('operator', 20);
            $table->json('value');
            $table->string('custom_field_key', 60)->nullable();

            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_rules');
    }
};
