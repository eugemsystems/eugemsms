<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-06 §2/BR-FIN-06-003. Append-only audit of every conversion
 * performed — every conversion is re-derivable and challengeable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_line_id')->nullable()->constrained('journal_lines')->nullOnDelete();
            $table->string('context_type', 60);
            $table->unsignedBigInteger('context_id')->nullable();
            $table->char('from_currency', 3);
            $table->bigInteger('from_amount_minor');
            $table->char('to_currency', 3);
            $table->bigInteger('to_amount_minor');
            $table->foreignId('exchange_rate_id')->constrained('exchange_rates');
            $table->decimal('rate_used', 20, 10);
            $table->timestamp('rate_effective_from');
            $table->timestamp('converted_at');

            $table->index(['school_id', 'converted_at']);
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_conversions');
    }
};
