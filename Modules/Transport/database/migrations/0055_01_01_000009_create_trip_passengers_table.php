<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-009/010 — the manifest itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_passengers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips');
            $table->foreignId('student_id')->nullable()->constrained('students');
            $table->foreignId('staff_id')->nullable()->constrained('staff');
            $table->foreignId('stop_id')->nullable()->constrained('route_stops');
            $table->timestamp('boarded_at')->nullable();
            $table->timestamp('alighted_at')->nullable();
            $table->string('boarding_method', 20)->nullable();
            $table->timestamp('guardian_notified_at')->nullable();
            $table->string('status', 20);

            $table->unique(['trip_id', 'student_id'], 'trip_passengers_trip_student_unique');
            $table->index(['school_id', 'student_id', 'trip_id'], 'trip_passengers_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_passengers');
    }
};
