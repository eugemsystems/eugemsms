<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2. An entrance examination sitting for an intake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrance_exams', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_id')->constrained('intakes');
            $table->string('name', 150);
            $table->date('exam_date');
            $table->time('start_time');
            $table->string('venue', 150)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->json('papers');
            $table->decimal('pass_mark_percent', 5, 2)->nullable();
            $table->string('status', 20);
            $table->index(['school_id', 'intake_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrance_exams');
    }
};
