<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-12 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('report_type', 30);
            $table->json('structure');
            $table->smallInteger('comparative_periods')->default(1);
            $table->boolean('show_variance')->default(true);
            $table->boolean('show_budget')->default(false);
            $table->boolean('is_system')->default(false);

            $table->unique(['school_id', 'code'], 'report_definitions_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
