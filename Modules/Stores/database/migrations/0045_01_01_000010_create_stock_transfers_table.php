<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-018/019.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_number', 40);
            $table->foreignId('from_store_id')->constrained('stores');
            $table->foreignId('to_store_id')->constrained('stores');
            $table->string('reason', 255);
            $table->string('status', 20);
            $table->foreignId('dispatched_by')->nullable()->constrained('users');
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->text('discrepancy_note')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->timestamps();

            $table->unique(['school_id', 'transfer_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
