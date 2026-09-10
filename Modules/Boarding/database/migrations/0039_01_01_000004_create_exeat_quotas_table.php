<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §2/BR-BRD-03-005.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exeat_quotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('exeat_type_id')->constrained('exeat_types');
            $table->smallInteger('allowed');
            $table->smallInteger('used')->default(0);
            $table->smallInteger('pending')->default(0);

            $table->unique(['school_id', 'student_id', 'term_id', 'exeat_type_id'], 'exeat_quotas_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exeat_quotas');
    }
};
