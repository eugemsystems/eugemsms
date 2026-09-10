<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §2. Feeds BR-CORE-03-004's teaching-days computation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('type', 20);
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_holidays');
    }
};
