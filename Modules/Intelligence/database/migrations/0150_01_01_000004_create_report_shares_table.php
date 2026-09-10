<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-005.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('custom_reports')->cascadeOnDelete();
            $table->string('shared_with_type', 20);
            $table->unsignedBigInteger('shared_with_id');
            $table->boolean('can_edit')->default(false);
            $table->foreignId('shared_by')->constrained('users');
            $table->timestamps();

            $table->unique(['report_id', 'shared_with_type', 'shared_with_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_shares');
    }
};
