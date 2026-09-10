<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-01 §3. Cache only — never authoritative. See
 * `account_balances`' migration note on why this isn't `BelongsToSchool`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subledger_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('subledger_type', 30);
            $table->unsignedBigInteger('subledger_id');
            $table->foreignId('account_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->char('currency', 3);
            $table->bigInteger('opening_minor')->default(0);
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->bigInteger('closing_minor')->default(0);
            $table->timestamp('rebuilt_at');

            $table->unique(['school_id', 'subledger_type', 'subledger_id', 'account_id', 'term_id', 'currency'], 'subledger_balances_unique_key');
            $table->index(['school_id', 'subledger_type', 'subledger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subledger_balances');
    }
};
