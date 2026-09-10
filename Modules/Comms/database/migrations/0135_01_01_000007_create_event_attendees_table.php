<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §2/BR-COM-06-006/007. Two columns beyond the spec's
 * own literal schema, both documented deviations:
 *
 *  - `ad_hoc_charge_id` — the spec names `FIN-02`/`FIN-04` linkage but
 *    doesn't give it a column; mirrors the EXACT real precedent
 *    already shipped in `Modules\Sport\Models\ActivityMembership`
 *    (Book H2 OPS-07), which raises its own activity fee through the
 *    same `Modules\Finance\Domain\Actions\CreateAdHocChargeAction`
 *    and stores the result the identical way. `ticket_receipt_id`
 *    (the spec's own column) is populated once a human/front-desk
 *    process attaches a real `Modules\Finance\Models\Receipt` — see
 *    `Modules\Comms\Domain\Actions\ConfirmEventTicketPaymentAction`'s
 *    own docblock for why: no code anywhere in this codebase turns a
 *    `pending` `AdHocCharge` into an `Invoice`/`Receipt` automatically
 *    (confirmed by research — `AdHocChargeRaised` has zero listeners,
 *    `Fin02BillingTest` itself stops at `status === 'pending'`), so
 *    COM-06 cannot honestly claim an automated settlement bridge that
 *    doesn't exist yet in Book B.
 *  - `waitlist_position` — `status` gains a `waitlisted` value beyond
 *    the spec's literal four (BR-COM-06-007, "mirroring BRD-01's
 *    hostel waiting list" — `Modules\Boarding\Models\HostelWaitingListEntry`'s
 *    own `position` column, reused here on the SAME row rather than a
 *    parallel table, since an event attendee row already carries the
 *    identity a hostel waitlist entry has to invent separately).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->string('attendee_type', 20);
            $table->string('attendee_name', 200);
            $table->foreignId('guardian_id')->nullable()->constrained('guardians');
            $table->tinyInteger('party_size')->default(1);
            $table->foreignId('ad_hoc_charge_id')->nullable()->constrained('ad_hoc_charges');
            $table->unsignedBigInteger('ticket_receipt_id')->nullable();
            $table->smallInteger('waitlist_position')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'registration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};
