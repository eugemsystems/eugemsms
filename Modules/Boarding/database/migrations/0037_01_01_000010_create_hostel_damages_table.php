<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/BR-BRD-01-012/013/014 ⭐. `ad_hoc_charge_ids`
 * records every `FIN-02` `AdHocCharge` id raised for this damage
 * (plural — a shared-liability split raises one charge per liable
 * learner, BR-BRD-01-012/AC-BRD-01-005). `work_order_id` is a forward
 * reference to `OPS-02` (Book H), no FK yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_damages', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('hostel_id')->constrained('hostels');
            $table->foreignId('room_id')->nullable()->constrained('hostel_rooms');
            $table->foreignId('bed_id')->nullable()->constrained('hostel_beds');
            $table->foreignId('inspection_id')->nullable()->constrained('room_inspections');
            $table->string('damage_type', 40);
            $table->text('description');
            $table->json('photo_file_ids')->nullable();
            $table->bigInteger('estimated_cost_minor')->nullable();
            $table->bigInteger('actual_cost_minor')->nullable();
            $table->char('currency', 3);
            $table->string('liability', 20);
            $table->json('liable_student_ids')->nullable();
            $table->string('charge_status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->json('ad_hoc_charge_ids')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamp('reported_at');

            $table->index(['school_id', 'term_id', 'charge_status'], 'hostel_damages_charge_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_damages');
    }
};
