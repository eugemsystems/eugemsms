<?php

declare(strict_types=1);

namespace Modules\Sport\Providers;

use Illuminate\Support\Facades\Event;
use Modules\Boarding\Domain\Events\InspectionRecorded;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Sport\Domain\Listeners\RecordHousePointsFromBehaviourListener;
use Modules\Sport\Domain\Listeners\RecordHousePointsFromInspectionListener;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\ActivityMembership;
use Modules\Sport\Models\Award;
use Modules\Sport\Models\EquipmentIssue;
use Modules\Sport\Models\Fixture;
use Modules\Sport\Models\HouseCompetition;
use Modules\Sport\Models\HousePoint;
use Modules\Sport\Models\Team;
use Modules\Welfare\Domain\Events\BehaviourRecorded;
use Modules\Welfare\Domain\Events\PositiveBehaviourRecorded;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H2 Domain F: Operations & Estates — `OPS-07` Sport, Houses &
 * Co-curricular, the final module of Book H2, built after `OPS-06`
 * per the book's own order.
 *
 * Real cross-module wiring: `JoinActivityAction`/`SelectFixtureSquadAction`
 * gate on `Modules\Welfare`'s real `MedicalCondition.
 * affects_physical_activity` (BRD-06); `JoinActivityAction` bills
 * through `Modules\Finance`'s real `CreateAdHocChargeAction` (FIN-02);
 * `ConfirmFixtureAction` creates a real `Modules\Transport` trip
 * (OPS-01, `ScheduleTripAction`) for an away fixture and a real
 * `Modules\Facilities` internal booking (OPS-05, `RequestBookingAction`)
 * for a home one; `MarkSquadRollStatusForFixtureAction` uses
 * `Modules\Boarding`'s real `MarkRollCallAction` with the
 * already-recognized `'fixture'` roll-call status (BRD-02);
 * `RecordFixtureInjuryAction` reuses `Modules\Welfare`'s real
 * `RecordHealthIncidentAction` (BRD-06); house points listen live off
 * the real `BehaviourRecorded`/`PositiveBehaviourRecorded`
 * (`Modules\Welfare`, BRD-07) and `InspectionRecorded`
 * (`Modules\Boarding`, BRD-01) domain events — additive listeners,
 * never edits to those already-gated actions.
 *
 * Three additive, documented extensions beyond the spec's own literal
 * §2 schema — real gaps, not spec columns invented for convenience:
 *  - `activity_memberships.ad_hoc_charge_id` (renamed from the
 *    spec's literal `fee_line_id`) — `CreateAdHocChargeAction`
 *    produces an `AdHocCharge`, never a `LearnerFeeLine` directly; see
 *    that migration's own docblock.
 *  - `health_incidents.fixture_id` (new, additive column on an
 *    already-shipped Book G table) — no structured link from a health
 *    incident to a fixture existed; `activity_at_time` is free text.
 *  - `equipment_issues` (new table) — `Modules\Stores`'s Book H1
 *    asset-custodian mechanism (`ChangeAssetCustodianAction`) is
 *    staff-only and has no expected-return-date/overdue concept;
 *    sports kit is issued to individual learners with a season-end
 *    return expectation.
 *
 * Two documented deferrals: BR-OPS-07-008's fourth house-points
 * source, academic results (no "academic result posted" domain event
 * exists yet to listen for — use `RecordManualHousePointsAction` in
 * the meantime), and BR-OPS-07-010's "carries into the alumni record
 * on graduation" (Book K, not built yet).
 */
class SportServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Sport';

    protected string $nameLower = 'sport';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerListeners();
    }

    private function registerListeners(): void
    {
        Event::listen(BehaviourRecorded::class, RecordHousePointsFromBehaviourListener::class);
        Event::listen(PositiveBehaviourRecorded::class, RecordHousePointsFromBehaviourListener::class);
        Event::listen(InspectionRecorded::class, RecordHousePointsFromInspectionListener::class);
    }

    /**
     * Book H2 OPS-07 §4.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['sport.fixture_selection', ['student.first_name', 'opponent', 'fixture_date', 'venue'], false],
            ['sport.fixture_injury_guardian_notified', ['student.first_name', 'opponent', 'incident_type'], true],
            ['sport.equipment_overdue', ['issue.id'], false],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['email', 'sms'],
                defaultAudience: 'guardian',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book H2 OPS-07 has no settings table of its own in the spec —
     * these are the ones its own business rules imply a school needs
     * to configure.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['sport.behaviour_house_points_weight', 'decimal', '1', 'Multiplier applied to a behaviour record\'s own points before it becomes a house point (BR-OPS-07-008).'],
            ['sport.inspection_house_points_weight', 'decimal', '1', 'Multiplier applied to a room inspection\'s percentage score (scaled to 10) before it becomes a house point (BR-OPS-07-008).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'OPS',
                'group_key' => explode('.', $key)[0],
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => 'text',
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
        TenantModelRegistry::register(Activity::class, fn (School $school): Activity => Activity::factory()->for($school)->create());

        TenantModelRegistry::register(ActivityMembership::class, fn (School $school): ActivityMembership => ActivityMembership::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Team::class, fn (School $school): Team => Team::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Fixture::class, fn (School $school): Fixture => Fixture::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(HouseCompetition::class, fn (School $school): HouseCompetition => HouseCompetition::factory()->for($school)->create());

        TenantModelRegistry::register(HousePoint::class, fn (School $school): HousePoint => HousePoint::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Award::class, fn (School $school): Award => Award::factory()->for($school)->create());

        TenantModelRegistry::register(EquipmentIssue::class, fn (School $school): EquipmentIssue => EquipmentIssue::factory()->create(['school_id' => $school->id]));
    }
}
