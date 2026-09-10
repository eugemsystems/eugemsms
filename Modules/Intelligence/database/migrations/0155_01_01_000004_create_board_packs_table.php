<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-02 §2/BR-INT-02-006.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_packs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->json('sections_included');
            $table->foreignId('document_id')->nullable()->constrained('files');
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamp('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_packs');
    }
};
