<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-004. `licence_number` is stored `TEXT`,
 * not `VARCHAR`, and cast `encrypted` on the model — the spec marks
 * it ENCRYPTED, and Laravel's encrypted cast produces a payload wider
 * than a raw licence number, matching `Modules\People\Models\Staff`'s
 * own encrypted-field columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff');
            $table->text('licence_number');
            $table->json('licence_classes');
            $table->date('licence_expires_on');
            $table->string('defensive_driving_cert', 60)->nullable();
            $table->date('defensive_expires_on')->nullable();
            $table->date('medical_certificate_on')->nullable();
            $table->date('medical_expires_on')->nullable();
            $table->date('retest_due_on')->nullable();
            $table->smallInteger('years_experience')->nullable();
            $table->string('status', 20);
            $table->string('suspension_reason', 255)->nullable();
            $table->smallInteger('incident_count')->default(0);

            $table->unique(['school_id', 'staff_id'], 'drivers_school_staff_unique');
            $table->index(['school_id', 'licence_expires_on'], 'drivers_licence_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
