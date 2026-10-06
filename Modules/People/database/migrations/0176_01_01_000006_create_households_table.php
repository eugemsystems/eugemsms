<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3. A family grouping for sibling discounts and combined statements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->foreignId('head_guardian_id')->nullable()->constrained('guardians');
            $table->string('address_line_1', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('combined_statement')->default(true);
            $table->boolean('sibling_discount_eligible')->default(true);
            $table->timestamps();
            $table->index(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
