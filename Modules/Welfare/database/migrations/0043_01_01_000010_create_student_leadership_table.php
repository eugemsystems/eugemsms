<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/BR-BRD-07-020.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_leadership', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->string('role_title', 80);
            $table->string('scope_type', 20)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->json('granted_permissions')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('appointed_by')->constrained('users');
            $table->string('status', 20);
            $table->string('revocation_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_leadership');
    }
};
