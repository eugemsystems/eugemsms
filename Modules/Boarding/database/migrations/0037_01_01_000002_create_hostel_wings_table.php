<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_wings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels');
            $table->string('code', 20);
            $table->string('name', 80);
            $table->string('floor', 20)->nullable();
            $table->foreignId('supervisor_staff_id')->nullable()->constrained('staff');
            $table->foreignId('prefect_student_id')->nullable()->constrained('students');
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'hostel_id', 'code'], 'hostel_wings_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_wings');
    }
};
