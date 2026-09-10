<?php

declare(strict_types=1);

namespace Modules\Security\Providers;

use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Security\Models\Contractor;
use Modules\Security\Models\ContractorSiteVisit;
use Modules\Security\Models\ContractorWorker;
use Modules\Security\Models\EmergencyDrill;
use Modules\Security\Models\KeyAndCard;
use Modules\Security\Models\KeyIssue;
use Modules\Security\Models\LostProperty;
use Modules\Security\Models\MusterMark;
use Modules\Security\Models\OccurrenceBookEntry;
use Modules\Security\Models\Patrol;
use Modules\Security\Models\PatrolRoute;
use Modules\Security\Models\PatrolScan;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H2 Domain F: Operations & Estates — `OPS-06` Security, Gate &
 * Access Control, built after `OPS-05` per the book's own order.
 *
 * Real cross-module wiring in this pass: `AssembleMusterRollAction`
 * ⭐⭐ aggregates live from five already-built modules —
 * `Modules\Boarding`'s `BedAllocation`/`Exeat`/`VisitorLogEntry`
 * (BRD-01/03), `Modules\Academic`'s `AttendanceRecord` (ACA-04),
 * `Modules\People`'s `Staff`/`LeaveRequest` (PPL-04), and
 * `Modules\Welfare`'s `SickBayAdmission` (BRD-06) — no shadow
 * "who's on site" table kept independently; `CompleteMusterAction`
 * opens a real `BRD-02` `MissingLearnerIncident` for every
 * unaccounted learner through `OpenMissingLearnerIncidentAction`'s
 * own `executeForOverdueExeat()` entry point (no roll call, default
 * escalation profile — exactly this shape already); `patrol_scans.
 * checkpoint_id` is a real FK into `BRD-02`'s own
 * `movement_checkpoints`; `RecordOccurrenceAction`'s gapless
 * `entry_number` is `CORE-06`'s own `AllocateNumberAction` sequence,
 * not a bespoke counter.
 *
 * Two additive, documented extensions beyond the spec's own literal
 * §2 schema — both real gaps its own §3 muster/gate-access
 * pseudocode assumes exist, not spec columns this module invented for
 * convenience:
 *  - `contractor_site_visits` (new table) — no gate sign-in/out log
 *    for contractor workers existed anywhere; `Modules\Boarding`'s own
 *    `VisitorLogEntry` is scoped to visitors/guardians/students only.
 *  - `muster_marks` (new table) — `emergency_drills` only carries
 *    aggregate headcount columns; nothing tracked which individual
 *    person a marshal tapped present, which the live unaccounted list
 *    needs per-person state for.
 *  - `medical_conditions.requires_evacuation_assistance` (new,
 *    additive column on an already-shipped Book G table) — no
 *    discrete mobility/evacuation-assistance flag existed anywhere;
 *    the closest field is free text, unsafe to substring-match for a
 *    safety-critical muster flag.
 *
 * One deliberate boundary: offline caching and the 15-minute device
 * refresh (BR-OPS-06-008's own second half, AC-OPS-06-002) are
 * client/API concerns — no API or mobile layer exists yet for any
 * module in this codebase, the same boundary held everywhere else.
 */
class SecurityServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Security';

    protected string $nameLower = 'security';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
    }

    /**
     * Book H2 OPS-06 §4.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['security.occurrence_immediate_notice', ['category', 'description'], true],
            ['security.patrol_missed', ['patrol.id'], true],
            ['security.patrol_incomplete', ['patrol.id'], false],
            ['security.key_overdue', ['issue.id'], true],
            ['security.contractor_site_access_refused', ['worker.full_name', 'reason'], false],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['email'],
                defaultAudience: 'staff',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book H2 OPS-06 has no settings table of its own in the spec —
     * these are the ones its own business rules imply a school needs
     * to configure.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['security.head_staff_id', 'int', '', 'Staff record for the head, notified immediately on an intrusion, fire or medical occurrence book entry.'],
            ['security.patrol_missed_grace_minutes', 'int', '15', 'Minutes past its own scheduled time before a still-scheduled patrol is considered missed.'],
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
        TenantModelRegistry::register(Contractor::class, fn (School $school): Contractor => Contractor::factory()->for($school)->create());

        TenantModelRegistry::register(ContractorWorker::class, fn (School $school): ContractorWorker => ContractorWorker::factory()->for($school)->create());

        TenantModelRegistry::register(ContractorSiteVisit::class, fn (School $school): ContractorSiteVisit => ContractorSiteVisit::factory()->for($school)->create());

        TenantModelRegistry::register(PatrolRoute::class, fn (School $school): PatrolRoute => PatrolRoute::factory()->for($school)->create());

        TenantModelRegistry::register(Patrol::class, fn (School $school): Patrol => Patrol::factory()->for($school)->create());

        TenantModelRegistry::register(PatrolScan::class, function (School $school): PatrolScan {
            $patrol = Patrol::factory()->for($school)->create();
            $checkpoint = MovementCheckpoint::factory()->for($school)->create();

            return PatrolScan::factory()->create(['school_id' => $school->id, 'patrol_id' => $patrol->id, 'checkpoint_id' => $checkpoint->id]);
        });

        TenantModelRegistry::register(OccurrenceBookEntry::class, fn (School $school): OccurrenceBookEntry => OccurrenceBookEntry::factory()->for($school)->create());

        TenantModelRegistry::register(KeyAndCard::class, fn (School $school): KeyAndCard => KeyAndCard::factory()->for($school)->create());

        TenantModelRegistry::register(KeyIssue::class, fn (School $school): KeyIssue => KeyIssue::factory()->for($school)->create());

        TenantModelRegistry::register(LostProperty::class, fn (School $school): LostProperty => LostProperty::factory()->for($school)->create());

        TenantModelRegistry::register(EmergencyDrill::class, fn (School $school): EmergencyDrill => EmergencyDrill::factory()->for($school)->create());

        TenantModelRegistry::register(MusterMark::class, fn (School $school): MusterMark => MusterMark::factory()->for($school)->create());
    }
}
