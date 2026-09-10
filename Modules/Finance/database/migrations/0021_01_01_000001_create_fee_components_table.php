<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2. The fee catalogue — 'TUITION', 'LEVY', 'BOARDING'.
 * Each posts through its own income/debtor account pair, so a single
 * billing run routinely spans several GL account pairs in one journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_components', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->string('category', 30);
            $table->foreignId('income_account_id')->constrained('accounts');
            $table->foreignId('debtor_account_id')->constrained('accounts');
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
            $table->char('default_currency', 3);
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_fiscalisable')->default(false);
            $table->string('tax_category', 20)->default('exempt');
            $table->smallInteger('allocation_priority')->default(100);
            $table->boolean('counts_toward_report_gate')->default(true);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_components');
    }
};
