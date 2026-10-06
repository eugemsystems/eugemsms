<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2. A staff member's qualifications, verified against the certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('qualification_type', 30);
            $table->string('title', 200);
            $table->string('institution', 200);
            $table->char('country', 2)->default('ZW');
            $table->unsignedSmallInteger('year_obtained')->nullable();
            $table->string('grade_class', 40)->nullable();
            $table->json('subjects')->nullable();
            $table->foreignId('certificate_file_id')->nullable()->constrained('files');
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->index(['school_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_qualifications');
    }
};
