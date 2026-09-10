<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-013 ⭐ — APPEND-ONLY, two-person. A
 * different staff member must witness every receipt, administration
 * and disposal of controlled medication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_stock_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_stock_id')->constrained('clinic_stock');
            $table->string('action', 20);
            $table->decimal('quantity', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->foreignId('administration_id')->nullable()->constrained('medication_administrations');
            $table->foreignId('performed_by')->constrained('users');
            $table->foreignId('witnessed_by')->constrained('users');
            $table->timestamp('occurred_at');
            $table->string('notes', 255)->nullable();

            $table->index(['clinic_stock_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlled_stock_log');
    }
};
