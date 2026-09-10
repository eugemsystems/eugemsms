<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-12 §4/BR-FIN-12-010. `reason` is mandatory — an
 * acknowledgement with no explanation is not an acknowledgement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('close_check_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_id')->constrained('period_close_checklists');
            $table->string('check_key', 60);
            $table->text('reason');
            $table->foreignId('acknowledged_by')->constrained('users');
            $table->timestamp('acknowledged_at');
            $table->foreignId('approved_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('close_check_acknowledgements');
    }
};
