<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-05 §3. `source_file_id` is a plain reference — no CSV/OFX
 * upload/parsing pipeline is wired in this pass; `ImportBankStatementAction`
 * takes already-parsed line data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->date('statement_from');
            $table->date('statement_to');
            $table->bigInteger('opening_balance_minor');
            $table->bigInteger('closing_balance_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('source_file_id')->nullable();
            $table->integer('line_count')->default(0);
            $table->integer('matched_count')->default(0);
            $table->string('status', 20);
            $table->foreignId('imported_by')->constrained('users');
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
