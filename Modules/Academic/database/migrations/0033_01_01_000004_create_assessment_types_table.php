<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_types', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('category', 20);
            $table->decimal('default_weight_percent', 5, 2);
            $table->boolean('appears_on_report_card')->default(true);
            $table->boolean('is_examination')->default(false);
            $table->smallInteger('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'assessment_types_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_types');
    }
};
