<?php

declare(strict_types=1);

namespace Modules\Compliance\Providers;

use Modules\Compliance\Domain\Registry\PersonalDataTableRegistry;
use Modules\Compliance\Models\Consent;
use Modules\Compliance\Models\ConsentType;
use Modules\Compliance\Models\Contract;
use Modules\Compliance\Models\DataBreach;
use Modules\Compliance\Models\DataQualityCheck;
use Modules\Compliance\Models\DisposalQueueItem;
use Modules\Compliance\Models\GovernanceMinute;
use Modules\Compliance\Models\Policy;
use Modules\Compliance\Models\PolicyAcknowledgement;
use Modules\Compliance\Models\PrivacyNotice;
use Modules\Compliance\Models\ProcessingRegisterEntry;
use Modules\Compliance\Models\RetentionSchedule;
use Modules\Compliance\Models\StatutoryDocument;
use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Compliance\Models\SubjectAccessRequest;
use Modules\Compliance\Models\ThirdPartyProcessor;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Compliance\Models\ZimsecResult;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H3 CMP-01 — ZIMSEC Candidate Registration & Results, the first
 * module in this codebase to close the `ExaminationCandidateSet`
 * interface Book E's ACA-07 opened: `DeriveZimsecCandidatesAction`
 * reads confirmed `examination_candidates` rows and their student's
 * bio-data directly, never re-keying either (BR-CMP-01-001).
 * `ValidateZimsecCandidatesAction` reuses ACA-01's own real
 * `SubjectSelectionRuleEngine` for subject-count/pathway checks rather
 * than duplicating the ACA-01 maximum here (BR-CMP-01-005).
 * `BillZimsecEntryFeesAction` raises real `ad_hoc_charges` rows through
 * Book B FIN-02's `CreateAdHocChargeAction`.
 *
 * `zimsec_validation_rules` is deliberately NOT registered in
 * `TenantModelRegistry`: `school_id` is nullable there (a null row is a
 * system-wide default), so the model doesn't use `BelongsToSchool` and
 * the tenancy isolation generator — which asserts every registered
 * model's query is scoped by the current school — doesn't apply to it.
 *
 * `collected_minor`/`remitted_minor` on `zimsec_registrations` are
 * explicit bursar-recorded figures, not derived from the GL — see the
 * module's own migrations for why: nothing in this codebase turns an
 * `ad_hoc_charges` row into an invoice or a journal entry yet (a
 * pre-existing FIN-02/FIN-03 gap flagged separately, not this module's
 * to close).
 *
 * Book H3 CMP-02 — MoPSE Returns & EMIS Reporting.
 * `GenerateStatutorySchoolReturnAction` freezes `data_snapshot` on
 * first generation and never touches it again — the model's own
 * `booted()` guard enforces that — so a later regeneration returns a
 * diff against current live data instead (BR-CMP-02-001,
 * AC-CMP-02-002). `GenerateInspectionPackAction` draws attendance
 * registers from `ACA-04`, not `OPS-02` as the spec originally named
 * it (corrected inline in the spec file itself) and leaves
 * `policy_acknowledgements` explicitly null pending CMP-04.
 *
 * Book H3 CMP-03 — Data Protection, Consent & Privacy.
 * `consents` is append-only (withdrawal is a new state on the SAME
 * row, enforced by the model's own guard, never a new row or a
 * deletion — BR-CMP-03-001). `RecordConsentAction` resolves the
 * privacy notice version in force itself, from `privacy_notices` (a
 * table this spec's own data model omitted — added here, see the
 * migration's own docblock). `RecordDataBreachAction` escalates a
 * minors breach the same way `Modules\Welfare`'s real
 * `SafeguardingRouterImpl` already resolves its own lead — a single
 * per-school staff-id setting, not a role query (none exists in this
 * codebase yet). `PersonalDataTableRegistry` pre-registers only the
 * tables this module can directly confirm hold personal data — see
 * its own class docblock for the documented boundary on retrofitting
 * every other module's tables.
 *
 * Book H3 CMP-04 — Policy, Document Register & Retention. A new
 * `Policy` version is a new row (`CreatePolicyAction` also flips the
 * superseded version's own `status`) — `policy_acknowledgements` is
 * append-only against the FROZEN `policy_version` on each row, so a
 * later version never retroactively changes what an earlier
 * acknowledgement agreed to (AC-CMP-04-001).
 * `GenerateConsolidatedIncidentRegisterAction` pulls from
 * `HealthIncident`, `BehaviourRecord`, `OccurrenceBookEntry`,
 * `VehicleIncident` and this book's own `DataBreach` — safeguarding
 * is excluded by never being queried at all (BR-CMP-04-008).
 * `contracts` (this module's own addition — see its migration's
 * docblock) reuses `CheckDocumentExpiryAction`, the same expiry
 * mechanism `statutory_documents` uses.
 */
class ComplianceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Compliance';

    protected string $nameLower = 'compliance';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerPersonalDataTables();
    }

    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(ZimsecRegistration::class, fn (School $school): ZimsecRegistration => ZimsecRegistration::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ZimsecCandidate::class, fn (School $school): ZimsecCandidate => ZimsecCandidate::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ZimsecResult::class, fn (School $school): ZimsecResult => ZimsecResult::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(StatutorySchoolReturn::class, fn (School $school): StatutorySchoolReturn => StatutorySchoolReturn::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(DataQualityCheck::class, fn (School $school): DataQualityCheck => DataQualityCheck::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(PrivacyNotice::class, fn (School $school): PrivacyNotice => PrivacyNotice::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ConsentType::class, fn (School $school): ConsentType => ConsentType::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Consent::class, fn (School $school): Consent => Consent::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(RetentionSchedule::class, fn (School $school): RetentionSchedule => RetentionSchedule::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(DisposalQueueItem::class, fn (School $school): DisposalQueueItem => DisposalQueueItem::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SubjectAccessRequest::class, fn (School $school): SubjectAccessRequest => SubjectAccessRequest::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(DataBreach::class, fn (School $school): DataBreach => DataBreach::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ProcessingRegisterEntry::class, fn (School $school): ProcessingRegisterEntry => ProcessingRegisterEntry::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ThirdPartyProcessor::class, fn (School $school): ThirdPartyProcessor => ThirdPartyProcessor::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Policy::class, fn (School $school): Policy => Policy::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(PolicyAcknowledgement::class, fn (School $school): PolicyAcknowledgement => PolicyAcknowledgement::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(StatutoryDocument::class, fn (School $school): StatutoryDocument => StatutoryDocument::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(GovernanceMinute::class, fn (School $school): GovernanceMinute => GovernanceMinute::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Contract::class, fn (School $school): Contract => Contract::factory()->create(['school_id' => $school->id]));
    }

    /**
     * Book H3 CMP-03 §3/BR-CMP-03-005 ⭐ — see `PersonalDataTableRegistry`'s
     * own docblock for the documented scope boundary.
     */
    private function registerPersonalDataTables(): void
    {
        PersonalDataTableRegistry::register('students', 'PPL-01', 'Learner bio-data and enrolment record.');
        PersonalDataTableRegistry::register('staff', 'PPL-02', 'Staff bio-data and employment record.');
        PersonalDataTableRegistry::register('guardians', 'PPL-03', 'Guardian bio-data and contact details.');
        PersonalDataTableRegistry::register('zimsec_candidates', 'CMP-01', 'ZIMSEC candidate bio-data, a copy of student personal data.');
        PersonalDataTableRegistry::register('consents', 'CMP-03', 'Consent records naming the subject.');
    }

    /**
     * Book H3 CMP-01 §3.
     */
    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'zimsec.statement_of_entry',
            variables: ['student.first_name', 'exam_level', 'exam_series'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'guardian',
            isUrgent: false,
            isTransactional: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'compliance.data_breach_escalated',
            variables: ['breach_type', 'severity', 'subjects_affected'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'staff',
            isUrgent: true,
            isTransactional: true,
        ));
    }

    /**
     * Book H3 CMP-01 §3/BR-CMP-01-008/012. Deadline alert days and
     * pass-grade cutoffs are versioned config, never hard-coded — both
     * are ZIMSEC's own conventions, not this system's, and both have
     * changed over time.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['compliance.zimsec_alert_days', 'json', '[30,14,7,1]', 'Days before registration_closes_on that a deadline alert fires.'],
            ['compliance.zimsec_pass_grades_grade_7', 'string', '1,2,3,4', 'Comma-separated grades counted as a pass for ZIMSEC Grade 7 pass-rate analysis.'],
            ['compliance.zimsec_pass_grades_o_level', 'string', 'A*,A,B,C', 'Comma-separated grades counted as a pass for ZIMSEC O-Level pass-rate analysis.'],
            ['compliance.zimsec_pass_grades_a_level', 'string', 'A*,A,B,C,D,E', 'Comma-separated grades counted as a pass for ZIMSEC A-Level pass-rate analysis.'],
            ['compliance.statutory_return_alert_days', 'json', '[30,14,7]', 'Days before a statutory school return\'s due_date that a deadline alert fires.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'CMP',
                'group_key' => 'zimsec',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => 'text',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }

        $privacyDefinitions = [
            ['compliance.head_staff_id', 'int', '', 'Staff record for the head — notified automatically on a minors data breach (BR-CMP-03-011).'],
            ['compliance.dsar_response_days', 'int', '30', 'Statutory response window for a subject access request (placeholder pending confirmation against the Act\'s own text).'],
        ];

        foreach ($privacyDefinitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'CMP',
                'group_key' => 'privacy',
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
}
