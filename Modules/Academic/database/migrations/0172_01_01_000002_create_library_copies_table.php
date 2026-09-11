<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-10 §2/BR-ACA-10-001. `accession_number` is allocated
 * through `CORE-06` (`AllocateNumberAction`, `document_type` =
 * "library_accession") — gapless per school. This migration omits the
 * spec's `purchase_order_id` FK to `FIN-08` (procurement), which this
 * codebase has not built yet — see this module's build notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_copies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('library_items')->cascadeOnDelete();
            $table->string('accession_number', 30);
            $table->string('barcode', 60)->nullable();
            $table->string('condition', 20);
            $table->date('acquired_on')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['school_id', 'accession_number'], 'library_copies_accession_unique');
            $table->index(['school_id', 'item_id', 'status'], 'library_copies_item_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_copies');
    }
};
