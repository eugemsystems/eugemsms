<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-015/016. `covered_asset_ids` null means
 * the whole `category_id` is covered — `CheckUnderInsuranceAction`
 * reads whichever is set, never both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_insurance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('policy_number', 60);
            $table->string('insurer', 200);
            $table->string('policy_type', 40);
            $table->json('covered_asset_ids')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('asset_categories');
            $table->bigInteger('sum_insured_minor');
            $table->string('currency', 3);
            $table->bigInteger('premium_minor');
            $table->date('starts_on');
            $table->date('expires_on');
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->string('status', 20);

            $table->index(['school_id', 'expires_on', 'status'], 'insurance_expiry_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_insurance');
    }
};
