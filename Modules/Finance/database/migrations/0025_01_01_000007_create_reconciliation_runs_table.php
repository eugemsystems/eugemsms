<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-05 §5 ⭐/BR-FIN-05-013. Never auto-resolves — this row
 * records the exception list a human then works through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->string('scope', 30);
            $table->foreignId('gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->bigInteger('gateway_total_minor')->nullable();
            $table->bigInteger('receipts_total_minor')->nullable();
            $table->bigInteger('bank_total_minor')->nullable();
            $table->bigInteger('gl_total_minor')->nullable();
            $table->char('currency', 3);
            $table->bigInteger('variance_minor')->default(0);
            $table->integer('exception_count')->default(0);
            $table->json('exceptions')->nullable();
            $table->string('status', 20);
            $table->timestamp('ran_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->unique(['school_id', 'run_date', 'scope', 'gateway_id', 'bank_account_id'], 'reconciliation_runs_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
