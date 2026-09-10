<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-04 §2/BR-ACA-04-004/005/007.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_reason_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->boolean('counts_as_present')->default(false);
            $table->boolean('counts_toward_percentage')->default(true);
            $table->boolean('is_authorised')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->boolean('suppresses_notification')->default(false);
            $table->boolean('triggers_welfare_flag')->default(false);
            $table->smallInteger('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'attendance_reason_codes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_reason_codes');
    }
};
