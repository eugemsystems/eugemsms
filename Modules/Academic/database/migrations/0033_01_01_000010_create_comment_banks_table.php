<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_banks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20);
            $table->foreignId('subject_id')->nullable()->constrained();
            $table->string('grade_band', 20)->nullable();
            $table->string('text', 500);
            $table->integer('usage_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'scope', 'subject_id'], 'comment_banks_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_banks');
    }
};
