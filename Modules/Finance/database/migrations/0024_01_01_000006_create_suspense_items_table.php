<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-04 §2/§⭐/BR-FIN-04-010/011. Unidentified money — never
 * held off-ledger. `suggested_matches` is a simple scored candidate
 * list (admission number / surname / guardian name or phone), never
 * auto-applied — the cashier always confirms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspense_items', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained();
            $table->string('source', 30);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->bigInteger('resolved_minor')->default(0);
            $table->string('reference_text', 255)->nullable();
            $table->string('depositor_name', 200)->nullable();
            $table->date('deposit_date');
            $table->string('status', 20);
            $table->json('suggested_matches')->nullable();
            $table->integer('age_days')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();

            $table->index(['school_id', 'status', 'deposit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspense_items');
    }
};
