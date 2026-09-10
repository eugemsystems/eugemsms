<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_property', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('found_on');
            $table->string('description', 255);
            $table->string('found_location', 150)->nullable();
            $table->foreignId('found_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->string('status', 20);
            $table->foreignId('claimed_by_student_id')->nullable()->constrained('students');
            $table->timestamp('claimed_at')->nullable();
            $table->date('disposal_on')->nullable();

            $table->index(['school_id', 'status'], 'lost_property_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_property');
    }
};
