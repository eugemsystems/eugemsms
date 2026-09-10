<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_accounts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('utility_type', 30);
            $table->string('provider', 120);
            $table->string('account_number', 60);
            $table->string('tariff_code', 40)->nullable();
            $table->string('billing_mode', 20);
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->foreignId('expense_account_id')->constrained('accounts');
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'utility_type', 'account_number'], 'utility_accounts_school_type_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_accounts');
    }
};
