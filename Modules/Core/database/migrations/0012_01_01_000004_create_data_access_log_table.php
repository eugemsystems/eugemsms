<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-08 §2/BR-CORE-08-009. Reads of medical, safeguarding,
 * payroll, and bulk learner data land here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_access_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('access_type', 30);
            $table->string('resource_type', 60);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->integer('record_count')->nullable();
            $table->string('purpose', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at');

            $table->index(['school_id', 'resource_type', 'accessed_at']);
            $table->index(['user_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_access_log');
    }
};
