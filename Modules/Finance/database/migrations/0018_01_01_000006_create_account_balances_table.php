<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-01 §3. Cache only — never authoritative. Not
 * `BelongsToSchool`: written exclusively by the rebuild service, which
 * always supplies `school_id` explicitly, same reasoning as Book A's
 * CORE-08/12 infrastructure tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->char('currency', 3);
            $table->bigInteger('opening_minor')->default(0);
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->bigInteger('closing_minor')->default(0);
            $table->integer('line_count')->default(0);
            $table->unsignedBigInteger('last_line_id')->nullable();
            $table->timestamp('rebuilt_at');

            $table->unique(['school_id', 'account_id', 'term_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};
