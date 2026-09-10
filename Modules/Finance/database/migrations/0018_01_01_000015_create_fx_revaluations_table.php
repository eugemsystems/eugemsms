<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_revaluations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->date('revaluation_date');
            $table->foreignId('closing_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->json('accounts_revalued');
            $table->bigInteger('gain_minor')->default(0);
            $table->bigInteger('loss_minor')->default(0);
            $table->char('base_currency', 3);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('status', 20);
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('performed_at')->nullable();

            $table->unique(['school_id', 'term_id', 'revaluation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_revaluations');
    }
};
