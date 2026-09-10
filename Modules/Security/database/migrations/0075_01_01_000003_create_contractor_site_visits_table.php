<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §3 ⭐/BR-OPS-06-002 — NOT in the spec's own §2 data
 * model. The spec's own muster pseudocode reads "Contractor workers
 * ← signed in, not signed out", but nothing in this book's schema
 * tracks that: `contractor_workers` is a roster with no visit-level
 * sign-in/out state, and `Modules\Boarding`'s gate log
 * (`VisitorLogEntry`, BRD-03) is scoped to visitors/guardians/students
 * only, never contractors. This table closes that real gap, modelled
 * the same append-only, "only the sign-out half is ever mutated" way
 * `VisitorLogEntry` already does it — the established pattern for
 * exactly this problem shape in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_site_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_worker_id')->constrained('contractor_workers');
            $table->timestamp('signed_in_at');
            $table->foreignId('gate_staff_in')->constrained('users');
            $table->timestamp('signed_out_at')->nullable();
            $table->foreignId('gate_staff_out')->nullable()->constrained('users');

            $table->index(['school_id', 'contractor_worker_id', 'signed_out_at'], 'contractor_site_visits_worker_out_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_site_visits');
    }
};
