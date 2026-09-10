<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-10 §2. Every posting for an asset in this category
 * (capitalisation, depreciation, disposal) resolves its accounts from
 * here — an asset never carries its own account references.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->foreignId('asset_account_id')->constrained('accounts');
            $table->foreignId('accum_depreciation_account_id')->constrained('accounts');
            $table->foreignId('depreciation_expense_account_id')->constrained('accounts');
            $table->foreignId('disposal_account_id')->constrained('accounts');
            $table->string('default_method', 30);
            $table->decimal('default_useful_life_years', 4, 1)->nullable();
            $table->decimal('default_residual_percent', 5, 2)->default(0);
            $table->boolean('is_depreciable')->default(true);
            $table->smallInteger('verification_frequency_months')->default(12);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
