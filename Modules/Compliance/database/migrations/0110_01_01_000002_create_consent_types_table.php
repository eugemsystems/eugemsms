<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->text('description');
            $table->string('lawful_basis', 40);
            $table->boolean('is_withdrawable')->default(true);
            $table->boolean('required_for_enrolment')->default(false);
            $table->string('applies_to', 20);
            $table->smallInteger('renewal_frequency_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'consent_types_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_types');
    }
};
