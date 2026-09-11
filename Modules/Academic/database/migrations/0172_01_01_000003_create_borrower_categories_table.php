<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-10 §2/BR-ACA-10-002. Loan policy by borrower role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrower_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->smallInteger('max_concurrent_loans');
            $table->smallInteger('loan_period_days');
            $table->smallInteger('max_renewals')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'category'], 'borrower_categories_school_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_categories');
    }
};
