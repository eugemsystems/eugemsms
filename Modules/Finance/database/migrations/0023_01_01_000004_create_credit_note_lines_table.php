<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->foreignId('component_id')->constrained('fee_components');
            $table->string('description', 255);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);

            $table->index('credit_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_lines');
    }
};
