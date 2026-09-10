<?php

declare(strict_types=1);

namespace Modules\Boarding\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Boarding\Domain\Listeners\EndAllocationOnResidencyChangeListener;
use Modules\Boarding\Domain\Support\EloquentLiveOccupancyProvider;
use Modules\Boarding\Domain\Support\LiveOccupancyProvider;
use Modules\Boarding\Domain\Support\NullStoreIssuanceProvider;
use Modules\Boarding\Domain\Support\StoreIssuanceProvider;
use Modules\Boarding\Models\AllocationConstraint;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Boarding\Models\EscalationAction as EscalationActionModel;
use Modules\Boarding\Models\EscalationProfile;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatQuota;
use Modules\Boarding\Models\ExeatType;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelDamage;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\HostelWaitingListEntry;
use Modules\Boarding\Models\HostelWing;
use Modules\Boarding\Models\IssuableItem;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Boarding\Models\LaundryItem;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Boarding\Models\MealAttendance;
use Modules\Boarding\Models\MealRequisitionLine;
use Modules\Boarding\Models\MealService;
use Modules\Boarding\Models\MenuCycle;
use Modules\Boarding\Models\MenuDay;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\MovementLogEntry;
use Modules\Boarding\Models\Recipe;
use Modules\Boarding\Models\RecipeIngredient;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Boarding\Models\RoomInspection;
use Modules\Boarding\Models\VisitingDay;
use Modules\Boarding\Models\VisitingDayBooking;
use Modules\Boarding\Models\Visitor;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Domain\Events\LearnerResidencyChanged;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Domain E Boarding & Welfare. BRD-01's hostel/room/bed hierarchy and
 * allocation engine (`BedAllocationEngine`) joined in this pass:
 * gender segregation as an unconditional, un-overridable hard
 * constraint (`GenderMismatchException`, thrown from the Action layer
 * with no accompanying override parameter anywhere), bulk/single
 * allocation producing drafts only, mid-term movement that supersedes
 * rather than overwrites, out-of-service refusal while a room is
 * occupied, inspections, and damage charging through `FIN-02`'s
 * `CreateAdHocChargeAction` with an exact largest-remainder split for
 * shared liability. `LearnerResidencyChanged` (Book C PPL-01) now also
 * drives `EndAllocationOnResidencyChangeListener`. Deliberately
 * deferred: the waiting list's "offer with an expiry" mechanic (no
 * notification wiring yet — only position recompute is built), the
 * "same department"-style deputy fallback equivalent (none needed
 * here), and `house_id`/`OPS-02` work-order linkage (both forward
 * references, no owning table yet).
 *
 * BRD-02's roll call and missing-learner escalation engine joined in
 * this pass: roll points, server-dated roll calls with expected
 * counts derived live from `bed_allocations`, idempotent marking,
 * withdrawn-status pre-population (the one §4 source this codebase
 * can answer for real today), the ⭐⭐ escalation ladder
 * (`OpenMissingLearnerIncidentAction`/`AdvanceEscalationLadderAction`)
 * with time-driven advancement independent of acknowledgement,
 * mandatory action records, append-only incidents/escalation actions/
 * movement log enforced at the model level, and the `LiveOccupancyProvider`
 * interface (bound to `EloquentLiveOccupancyProvider`) that `BRD-04`
 * will consume. Deliberately deferred: the other five §4
 * pre-population sources (exeat/sick-bay/hospital/fixture/detention/
 * suspension — `BRD-03`/`BRD-06`/`BRD-07`/`OPS-07`, none built yet),
 * actual scheduled-command wiring for `AdvanceEscalationLadderAction`/
 * `CheckRollCallMissedAction` (the actions themselves are real and
 * directly testable against a fixed clock; the cron entry is a
 * deployment step), offline sync queue mechanics (a mobile/API
 * concern — the marking primitive itself is idempotent by design),
 * and `notify_role_id`/`notify_guardians` dispatch fan-out (only
 * `notify_staff_id` actually dispatches a notification in this pass).
 *
 * BRD-03's exeat/gate/visitor engine joined in this pass: exeat types
 * and quotas, `RequestExeatAction`'s guardian-initiation/fee-arrears/
 * suspension/one-off-authorisation checks, `ApproveExeatAction`'s
 * verification-code pass, and the ⭐⭐ `CollectionAuthorityChecker` —
 * every departure runs it with no fast path, and every path through
 * it (release or refusal) writes an append-only `collection_attempts`
 * row via `RecordDepartureAction`. `OpenRollCallAction` (BRD-02) now
 * also pre-populates `exeat` status for real, closing that half of
 * BR-BRD-02-003/BR-BRD-03-009. `CheckOverdueExeatAction` opens a
 * `BRD-02` missing-learner incident (now with a nullable `roll_call_id`
 * and a directly-stored `escalation_profile_id`, extended for exactly
 * this cross-module case) once an exeat is overdue past the
 * configured threshold. `student_guardian` (Book C PPL-03) gained
 * `may_authorise_exeat` for BR-BRD-03-014. Deliberately deferred: any
 * multi-stage `CORE-07` approval chain (single-step approval only,
 * matching this codebase's own established boundary everywhere else
 * CORE-07 is mentioned), the QR/pass document itself (`CORE-10`, not
 * built — only the verification code is real), entry-fee billing
 * (`FIN-02` ad hoc charge, not wired), and actual scheduled-command
 * wiring for `CheckOverdueExeatAction`/`CheckVisitorNotSignedOutAction`
 * (both actions are real and directly testable; the cron entry is a
 * deployment step).
 *
 * BRD-04's catering engine joined in this pass, in the spec's own
 * sanctioned "planning-only mode" (§0.3 — `FIN-09` is Book H, not
 * built): menus, recipes, and the ⭐ per-capita scaling
 * (`PlanMealServiceAction`) that reads `BRD-02`'s live present
 * occupancy rather than allocated beds are all real. `StoreIssuanceProvider`
 * is bound to `NullStoreIssuanceProvider` — costing and stock
 * availability report `null` (unavailable), never `0`/`false`, so a
 * meal is never shown costing nothing. `GetDietaryAlertsAction` is
 * the safeguarding query the serving terminal is meant to call on
 * every scan, not only the first. Deliberately deferred: everything
 * behind the real `FIN-09` store (actual stock depletion, FIFO cost,
 * farm-to-kitchen transfer, reorder-triggered purchase requisitions),
 * meal-attendance-driven `actual_served` automation (recorded
 * directly by the kitchen manager for now), and cost-per-serving
 * budget-variance alerting (`CostPerServingExceededBudget` needs a
 * budget figure this pass has nowhere to read from).
 *
 * BRD-05's linen/laundry engine joined in this pass, completing Book
 * F: the issuable-item catalogue, issue-with-tagging
 * (`IssueItemToLearnerAction`), and the report-then-approve shape
 * (matching `BRD-01`'s damage flow) for both degraded-condition damage
 * (`ReturnIssuedItemAction`/`ApproveIssuedItemDamageChargeAction` —
 * chargeable only within the catalogue item's `expected_lifespan_terms`,
 * never for normal wear beyond it) and loss
 * (`ReportItemLostAction`/`ApproveLostItemChargeAction`, which also
 * notifies the learner's primary guardian). Laundry cycles reconcile
 * items out against items back per learner
 * (`RecordLaundryReturnAction`), escalate a learner's second-or-later
 * discrepancy in a term to the hostel's housemaster, and refuse to
 * reconcile (`ReconcileLaundryCycleAction`) while any discrepancy is
 * still open. `CheckLinenClearanceAction` is a standalone, callable
 * clearance check — deliberately NOT wired into
 * `Modules\People\Domain\Actions\WithdrawStudentAction` (Book C
 * PPL-01, already shipped and tested); a future clearance workflow, or
 * that action itself if it later grows a final-clearance gate, calls
 * this rather than duplicating the query.
 */
class BoardingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Boarding';

    protected string $nameLower = 'boarding';

    public function register(): void
    {
        parent::register();

        $this->app->bind(LiveOccupancyProvider::class, EloquentLiveOccupancyProvider::class);
        $this->app->bind(StoreIssuanceProvider::class, NullStoreIssuanceProvider::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerEventListeners();
    }

    /**
     * Book F BRD-01 §4/BR-BRD-01-005. Closes the wiring
     * `Modules\People\Domain\Events\LearnerResidencyChanged` opened.
     */
    private function registerEventListeners(): void
    {
        Event::listen(LearnerResidencyChanged::class, EndAllocationOnResidencyChangeListener::class);
    }

    /**
     * Book F BRD-02 §8 ⭐⭐.
     */
    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'boarding.missing_learner_escalation',
            variables: ['step_number'],
            defaultChannels: ['push', 'sms', 'email'],
            defaultAudience: 'escalation_contact',
            isUrgent: true,
            isTransactional: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'boarding.linen_item_charged',
            variables: ['student.first_name', 'student.last_name', 'item.name', 'amount_minor'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'all_guardians',
            isTransactional: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'boarding.laundry_repeated_missing',
            variables: ['student_id'],
            defaultChannels: ['email'],
            defaultAudience: 'escalation_contact',
            isTransactional: true,
        ));
    }

    /**
     * Book F BRD-01 §7 (subset — catering/roll-call/exeat settings
     * join in their own later passes).
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['boarding.max_level_spread_per_room', 'int', '2', 'Maximum grade-level ordinal spread tolerated within one room (BR-BRD-01 soft constraint 7).'],
            ['boarding.prefer_house_hostel', 'bool', '1', 'Whether allocation prefers the learner\'s sports house hostel.'],
            ['boarding.siblings_same_hostel', 'bool', '1', 'Whether allocation prefers placing siblings in the same hostel.'],
            ['boarding.prefer_bed_continuity', 'bool', '1', 'Whether allocation prefers a returning learner\'s own bed from last term.'],
            ['boarding.damage_requires_approval', 'bool', '1', 'Whether a damage charge requires approval before it reaches FIN-02 (BR-BRD-01-012).'],
            ['boarding.damage_shared_liability_default', 'string', 'shared_room', 'Default liability basis for a reported damage.'],
            ['boarding.inspection_frequency_days', 'int', '7', 'Expected days between routine room inspections.'],
            ['boarding.waitlist_offer_expiry_hours', 'int', '48', 'Hours a waiting-list offer stays open before expiring.'],
            ['boarding.rollcall_grace_minutes', 'int', '10', 'Minutes after a roll call\'s scheduled time before it is flagged missed (BR-BRD-02-008).'],
            ['boarding.escalation_notify_guardians_step', 'int', '4', 'Escalation step number at which guardians are notified.'],
            ['boarding.escalation_guardian_notification_enabled', 'bool', '1', 'Whether guardian notification at the configured step is enabled.'],
            ['boarding.require_action_record_from_step', 'int', '1', 'Escalation step number from which a mandatory action record is required.'],
            ['boarding.rollcall_offline_cache_hours', 'int', '24', 'Hours of roll call data cached for offline marking.'],
            ['boarding.unauthorised_boundary_alert', 'bool', '1', 'Whether an unauthorised boundary crossing raises a security alert.'],
            ['boarding.rollcall_photo_grid', 'bool', '1', 'Whether the roll call screen shows a photo grid.'],
            ['boarding.exeat_min_notice_hours', 'int', '24', 'Default minimum notice required for an exeat request.'],
            ['boarding.exeat_escalate_if_out_of_province', 'bool', '1', 'Whether a destination outside the school\'s province adds a senior approval step.'],
            ['boarding.exeat_escalate_if_days_over', 'int', '2', 'Duration in days beyond which an exeat adds a senior approval step.'],
            ['boarding.exeat_block_on_fee_arrears', 'bool', '0', 'Whether exeat types configured to block on fee arrears actually do so.'],
            ['boarding.exeat_arrears_threshold_minor', 'int', '0', 'Fee arrears threshold, in minor currency units, above which an exeat is blocked.'],
            ['boarding.collection_requires_photo_id', 'bool', '1', 'Whether the collection authority check requires photo ID verification.'],
            ['boarding.overdue_opens_missing_incident_after_minutes', 'int', '180', 'Minutes an exeat may run overdue before a BRD-02 missing-learner incident opens (BR-BRD-03-015).'],
            ['boarding.visitor_signout_alert_hour', 'string', '19:00', 'Hour of day by which a visitor must be signed out.'],
            ['boarding.visitor_photo_required', 'bool', '1', 'Whether a visitor photograph is required at sign-in.'],
            ['catering.contingency_percent', 'int', '5', 'Percentage added to computed servings as contingency, reported separately (BR-BRD-04-003).'],
            ['catering.use_live_occupancy', 'bool', '1', 'Whether servings are computed from live present occupancy (BR-BRD-04-001) — cannot be disabled in this codebase.'],
            ['catering.meal_attendance_capture', 'bool', '0', 'Whether meal attendance is captured per learner rather than actual_served entered directly.'],
            ['catering.publish_menu_to_portal', 'bool', '1', 'Whether the published menu is visible to learners and guardians (BR-BRD-04-016).'],
            ['catering.dietary_alert_min_severity', 'string', 'moderate', 'Minimum severity that surfaces on the serving terminal.'],
            ['catering.costing_enabled', 'bool', '0', 'Whether costing is available — depends on FIN-09, not built in this pass.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'BRD',
                'group_key' => 'boarding',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => $dataType === 'bool' ? 'toggle' : 'text',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A Part 1.11's tenancy isolation test generator.
     */
    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(Hostel::class, fn (School $school): Hostel => Hostel::factory()->for($school)->create());

        TenantModelRegistry::register(HostelWing::class, function (School $school): HostelWing {
            $hostel = Hostel::factory()->for($school)->create();

            return HostelWing::factory()->create(['school_id' => $school->id, 'hostel_id' => $hostel->id]);
        });

        TenantModelRegistry::register(HostelRoom::class, function (School $school): HostelRoom {
            $hostel = Hostel::factory()->for($school)->create();

            return HostelRoom::factory()->create(['school_id' => $school->id, 'hostel_id' => $hostel->id]);
        });

        TenantModelRegistry::register(HostelBed::class, function (School $school): HostelBed {
            $hostel = Hostel::factory()->for($school)->create();
            $room = HostelRoom::factory()->create(['school_id' => $school->id, 'hostel_id' => $hostel->id]);

            return HostelBed::factory()->create(['school_id' => $school->id, 'room_id' => $room->id]);
        });

        TenantModelRegistry::register(BedAllocation::class, function (School $school): BedAllocation {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();
            $room = HostelRoom::factory()->create(['school_id' => $school->id, 'hostel_id' => $hostel->id]);
            $bed = HostelBed::factory()->create(['school_id' => $school->id, 'room_id' => $room->id]);

            return BedAllocation::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'bed_id' => $bed->id,
                'hostel_id' => $hostel->id,
                'room_id' => $room->id,
            ]);
        });

        TenantModelRegistry::register(AllocationConstraint::class, fn (School $school): AllocationConstraint => AllocationConstraint::factory()->for($school)->create());

        TenantModelRegistry::register(LearnerIncompatibility::class, function (School $school): LearnerIncompatibility {
            $studentA = Student::factory()->for($school)->create();
            $studentB = Student::factory()->for($school)->create();

            return LearnerIncompatibility::factory()->create([
                'school_id' => $school->id,
                'student_a_id' => $studentA->id,
                'student_b_id' => $studentB->id,
                'raised_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(HostelWaitingListEntry::class, function (School $school): HostelWaitingListEntry {
            [$student, $term, $year] = $this->studentAndTerm($school);

            return HostelWaitingListEntry::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(RoomInspection::class, function (School $school): RoomInspection {
            [, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();
            $room = HostelRoom::factory()->create(['school_id' => $school->id, 'hostel_id' => $hostel->id]);
            $staff = Staff::factory()->for($school)->create();

            return RoomInspection::factory()->create([
                'school_id' => $school->id,
                'term_id' => $term->id,
                'room_id' => $room->id,
                'inspector_staff_id' => $staff->id,
            ]);
        });

        TenantModelRegistry::register(HostelDamage::class, function (School $school): HostelDamage {
            [, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();

            return HostelDamage::factory()->create([
                'school_id' => $school->id,
                'term_id' => $term->id,
                'hostel_id' => $hostel->id,
                'reported_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(EscalationProfile::class, fn (School $school): EscalationProfile => EscalationProfile::factory()->for($school)->create());

        TenantModelRegistry::register(RollCallPoint::class, fn (School $school): RollCallPoint => RollCallPoint::factory()->for($school)->create());

        TenantModelRegistry::register(RollCall::class, function (School $school): RollCall {
            [, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();
            $point = RollCallPoint::factory()->for($school)->create();

            return RollCall::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id,
                'roll_call_point_id' => $point->id, 'hostel_id' => $hostel->id,
            ]);
        });

        TenantModelRegistry::register(RollCallRecord::class, function (School $school): RollCallRecord {
            [$student, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();
            $point = RollCallPoint::factory()->for($school)->create();
            $rollCall = RollCall::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id,
                'roll_call_point_id' => $point->id, 'hostel_id' => $hostel->id,
            ]);

            return RollCallRecord::factory()->create([
                'school_id' => $school->id, 'roll_call_id' => $rollCall->id, 'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(MissingLearnerIncident::class, function (School $school): MissingLearnerIncident {
            [$student, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();
            $point = RollCallPoint::factory()->for($school)->create();
            $rollCall = RollCall::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id,
                'roll_call_point_id' => $point->id, 'hostel_id' => $hostel->id,
            ]);

            return MissingLearnerIncident::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id,
                'student_id' => $student->id, 'roll_call_id' => $rollCall->id,
            ]);
        });

        TenantModelRegistry::register(EscalationActionModel::class, function (School $school): EscalationActionModel {
            [$student, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();
            $point = RollCallPoint::factory()->for($school)->create();
            $rollCall = RollCall::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id,
                'roll_call_point_id' => $point->id, 'hostel_id' => $hostel->id,
            ]);
            $incident = MissingLearnerIncident::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id,
                'student_id' => $student->id, 'roll_call_id' => $rollCall->id,
            ]);

            return EscalationActionModel::factory()->create(['school_id' => $school->id, 'incident_id' => $incident->id]);
        });

        TenantModelRegistry::register(MovementCheckpoint::class, fn (School $school): MovementCheckpoint => MovementCheckpoint::factory()->for($school)->create());

        TenantModelRegistry::register(MovementLogEntry::class, function (School $school): MovementLogEntry {
            [$student] = $this->studentAndTerm($school);
            $checkpoint = MovementCheckpoint::factory()->for($school)->create();

            return MovementLogEntry::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'checkpoint_id' => $checkpoint->id]);
        });

        TenantModelRegistry::register(ExeatType::class, fn (School $school): ExeatType => ExeatType::factory()->for($school)->create());

        TenantModelRegistry::register(Exeat::class, function (School $school): Exeat {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $exeatType = ExeatType::factory()->for($school)->create();

            return Exeat::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'student_id' => $student->id, 'exeat_type_id' => $exeatType->id,
            ]);
        });

        TenantModelRegistry::register(ExeatQuota::class, function (School $school): ExeatQuota {
            [$student, $term] = $this->studentAndTerm($school);
            $exeatType = ExeatType::factory()->for($school)->create();

            return ExeatQuota::factory()->create([
                'school_id' => $school->id, 'student_id' => $student->id, 'term_id' => $term->id, 'exeat_type_id' => $exeatType->id,
            ]);
        });

        TenantModelRegistry::register(CollectionAttempt::class, function (School $school): CollectionAttempt {
            [$student] = $this->studentAndTerm($school);

            return CollectionAttempt::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'gate_staff_id' => User::factory()]);
        });

        TenantModelRegistry::register(Visitor::class, fn (School $school): Visitor => Visitor::factory()->for($school)->create());

        TenantModelRegistry::register(VisitorLogEntry::class, function (School $school): VisitorLogEntry {
            $visitor = Visitor::factory()->for($school)->create();

            return VisitorLogEntry::factory()->create(['school_id' => $school->id, 'visitor_id' => $visitor->id, 'gate_staff_in' => User::factory()]);
        });

        TenantModelRegistry::register(VisitingDay::class, function (School $school): VisitingDay {
            [, $term] = $this->studentAndTerm($school);

            return VisitingDay::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);
        });

        TenantModelRegistry::register(VisitingDayBooking::class, function (School $school): VisitingDayBooking {
            [$student, $term] = $this->studentAndTerm($school);
            $visitingDay = VisitingDay::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);
            $guardian = Guardian::factory()->for($school)->create();

            return VisitingDayBooking::factory()->create([
                'school_id' => $school->id, 'visiting_day_id' => $visitingDay->id,
                'student_id' => $student->id, 'guardian_id' => $guardian->id,
            ]);
        });

        TenantModelRegistry::register(MenuCycle::class, fn (School $school): MenuCycle => MenuCycle::factory()->for($school)->create());

        TenantModelRegistry::register(Recipe::class, fn (School $school): Recipe => Recipe::factory()->for($school)->create());

        TenantModelRegistry::register(RecipeIngredient::class, function (School $school): RecipeIngredient {
            $recipe = Recipe::factory()->for($school)->create();

            return RecipeIngredient::factory()->create(['school_id' => $school->id, 'recipe_id' => $recipe->id]);
        });

        TenantModelRegistry::register(MenuDay::class, function (School $school): MenuDay {
            $cycle = MenuCycle::factory()->for($school)->create();

            return MenuDay::factory()->create(['school_id' => $school->id, 'cycle_id' => $cycle->id]);
        });

        TenantModelRegistry::register(MealService::class, function (School $school): MealService {
            [, $term] = $this->studentAndTerm($school);

            return MealService::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);
        });

        TenantModelRegistry::register(MealRequisitionLine::class, function (School $school): MealRequisitionLine {
            [, $term] = $this->studentAndTerm($school);
            $service = MealService::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);

            return MealRequisitionLine::factory()->create(['school_id' => $school->id, 'meal_service_id' => $service->id]);
        });

        TenantModelRegistry::register(DietaryRequirement::class, function (School $school): DietaryRequirement {
            [$student] = $this->studentAndTerm($school);

            return DietaryRequirement::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);
        });

        TenantModelRegistry::register(MealAttendance::class, function (School $school): MealAttendance {
            [$student, $term] = $this->studentAndTerm($school);
            $service = MealService::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);

            return MealAttendance::factory()->create(['school_id' => $school->id, 'meal_service_id' => $service->id, 'student_id' => $student->id]);
        });

        TenantModelRegistry::register(IssuableItem::class, fn (School $school): IssuableItem => IssuableItem::factory()->for($school)->create());

        TenantModelRegistry::register(LearnerIssuedItem::class, function (School $school): LearnerIssuedItem {
            [$student, $term] = $this->studentAndTerm($school);
            $item = IssuableItem::factory()->create(['school_id' => $school->id]);

            return LearnerIssuedItem::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id,
                'student_id' => $student->id, 'issuable_item_id' => $item->id,
                'issued_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(LaundryCycle::class, function (School $school): LaundryCycle {
            [, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();

            return LaundryCycle::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'hostel_id' => $hostel->id]);
        });

        TenantModelRegistry::register(LaundryItem::class, function (School $school): LaundryItem {
            [$student, $term] = $this->studentAndTerm($school);
            $hostel = Hostel::factory()->for($school)->create();
            $cycle = LaundryCycle::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'hostel_id' => $hostel->id]);

            return LaundryItem::factory()->create(['school_id' => $school->id, 'cycle_id' => $cycle->id, 'student_id' => $student->id]);
        });
    }

    /**
     * @return array{0: Student, 1: Term, 2: AcademicYear}
     */
    private function studentAndTerm(School $school): array
    {
        $student = Student::factory()->for($school)->create();
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

        return [$student, $term, $year];
    }
}
