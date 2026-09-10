<?php

declare(strict_types=1);

namespace Modules\Welfare\Providers;

use App\Models\User;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\IntegrityCheckRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Support\SafeguardingAuditChainCheck;
use Modules\Welfare\Domain\Support\SafeguardingRouter;
use Modules\Welfare\Domain\Support\SafeguardingRouterImpl;
use Modules\Welfare\Models\AgencyReferral;
use Modules\Welfare\Models\Appeal;
use Modules\Welfare\Models\BehaviourCategory;
use Modules\Welfare\Models\BehaviourPointBalance;
use Modules\Welfare\Models\BehaviourRecord;
use Modules\Welfare\Models\BehaviourTriggerRule;
use Modules\Welfare\Models\CaseAccessGrant;
use Modules\Welfare\Models\CaseEntry;
use Modules\Welfare\Models\ClinicObservation;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\Consultation;
use Modules\Welfare\Models\ControlledStockLogEntry;
use Modules\Welfare\Models\CounsellingSession;
use Modules\Welfare\Models\Detention;
use Modules\Welfare\Models\DisciplinaryCommittee;
use Modules\Welfare\Models\EmergencyCarePlan;
use Modules\Welfare\Models\ExternalReferral;
use Modules\Welfare\Models\HealthIncident;
use Modules\Welfare\Models\HealthScreening;
use Modules\Welfare\Models\Immunisation;
use Modules\Welfare\Models\MedicalCondition;
use Modules\Welfare\Models\MedicalConsent;
use Modules\Welfare\Models\MedicalRecord;
use Modules\Welfare\Models\MedicationAdministration;
use Modules\Welfare\Models\Prescription;
use Modules\Welfare\Models\RiskAssessment;
use Modules\Welfare\Models\SafeguardingCase;
use Modules\Welfare\Models\SafeguardingConcern;
use Modules\Welfare\Models\Sanction;
use Modules\Welfare\Models\SanctionType;
use Modules\Welfare\Models\SickBayAdmission;
use Modules\Welfare\Models\StudentLeadership;
use Modules\Welfare\Models\VulnerableLearnerRegistration;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Domain E Welfare & Pastoral (Book G, `BRD-06` -> `BRD-08`).
 *
 * BRD-06's health/clinic engine joined in this pass: the three-tier
 * visibility model (`ResolveMedicalTierAction`/`MedicalTier`,
 * `CareResponsibilityResolver` scoped to what actually exists today —
 * class teacher and hostel housemaster/matron, since no duty-roster or
 * activity-leader table exists yet), condition declaration that feeds
 * `BRD-04` dietary requirements with `public_summary` only and closes
 * `BRD-01`'s medical-proximity/mobility-ground-floor hard constraints
 * for real via `AccommodationConstraintResolver`, sick bay admission/
 * discharge and external referral (closing `BRD-02`'s `sick_bay`/
 * `hospital` roll-status stubs — `OpenRollCallAction` now queries both
 * directly), the ⭐⭐ `AdministerMedicationAction` (consent resolution
 * naming the missing consent, expiry refusal, two-person controlled
 * witness, emergency-provision path recorded loudly not silently),
 * outbreak threshold detection, and `SecondaryEncrypted` — a Core-level
 * cast added in this pass for clinical/safeguarding free text, keyed
 * separately from general application encryption (derived via HMAC
 * from `APP_KEY`, not a KMS-held secret — see its own docblock).
 * Deliberately deferred: duty-roster/activity-leader care
 * responsibility and catering-staff-for-dietary-only (no owning table
 * yet), `FIN-09`-backed real stock depletion (`clinic_stock` stays
 * lightweight, matching `BRD-04`'s own catering deferral), actual
 * scheduled-command wiring for stock-expiry alerts (the expiry check
 * itself is real at the point of administration), and any Http/API
 * layer (consistent with every module in this codebase to date).
 *
 * BRD-07's discipline/conduct engine joined in this pass:
 * `RecordBehaviourAction` records both polarities identically and
 * routes an `is_safeguarding_trigger` category through
 * `SafeguardingRouter` (bound to `NullSafeguardingRouter` until
 * `BRD-08` exists later in this book) while pausing the disciplinary
 * process for real regardless of that binding. `RebuildBehaviourPointBalanceAction`
 * closes `ACA-05`'s `conduct_grade` stub —
 * `Modules\Academic\Domain\Actions\ComputeTermResultsAction` now calls
 * it directly. `IssueSanctionAction` requires a disciplinary-committee
 * record with the learner's own statement (or an explicit decline) for
 * any `requires_committee` sanction, and refuses a boarder's
 * campus-removing sanction without recorded supervision/transport
 * arrangements (`sanctions.boarding_arrangements`, added in this pass
 * — not in the spec's own §2 table but required by its own §4 rule).
 * `ScheduleDetentionAction`/`IssueSanctionAction` close `BRD-02`'s
 * `detention`/`suspended` roll-status stubs — `OpenRollCallAction` now
 * queries `detentions`/`sanctions` directly. `EvaluateTriggerRulesAction`
 * reconciles "automatic sanctioning is available" with "no sanction
 * applied by the system alone" via a school-configured
 * `behaviour.automatic_sanction_issuer_user_id` — a real named person,
 * not an anonymous system actor. Deliberately deferred: a sports-
 * fixture clash check for detention scheduling (`OPS-07`, not built)
 * and `CORE-07`'s real multi-step approval chain (this pass uses a
 * single severity-threshold gate, matching this codebase's own
 * established `CORE-07` boundary everywhere else it's mentioned).
 *
 * BRD-08's inverted-access engine joined in this pass, closing Book G:
 * `SafeguardingRouter` is now bound to `SafeguardingRouterImpl` (real —
 * `BRD-07`'s stub is closed for good). `ViewSafeguardingCaseAction` is
 * the ⭐⭐ policy — vendor/impersonation hard-excluded as the FIRST
 * check with no override, then lead, then an active per-case grant,
 * then break-glass (loud: alerts lead and deputy, still logged), else
 * denied — every branch writing to the SEPARATE, hash-chained
 * `safeguarding_audit` stream (`RecordSafeguardingAuditEntryAction`/
 * `SafeguardingAuditChainCheck`, registered as an `IntegrityCheck`
 * alongside `FinancialAuditChainCheck`). `ReportAnonymousConcernAction`
 * stores no reporter identity at all — structurally absent, not
 * encrypted. `OpenSafeguardingCaseAction` propagates only the
 * existence of `has_safeguarding_flag` to `PPL-01`, never category or
 * detail. Deliberately deferred: the HTTP-middleware exclusion of
 * anonymous submissions from request/application logs (no Http layer
 * exists yet in this codebase for any module), a real KMS-backed
 * encryption key and DB-grant REVOKE (both share `BRD-06`/`CORE-08`'s
 * own already-documented deferral), and the general export/report-
 * builder exclusion rule (BR-BRD-08-021) — trivially satisfied today
 * since no such export/report-builder/BI system exists anywhere in
 * this codebase yet for it to leak into.
 */
class WelfareServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Welfare';

    protected string $nameLower = 'welfare';

    public function register(): void
    {
        parent::register();

        $this->app->bind(SafeguardingRouter::class, SafeguardingRouterImpl::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerIntegrityChecks();
    }

    /**
     * Book G BRD-08 §5/BR-BRD-08-006.
     */
    private function registerIntegrityChecks(): void
    {
        IntegrityCheckRegistry::register(new SafeguardingAuditChainCheck);
    }

    /**
     * Book G BRD-06 §7.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['health.emergency_treatment_proceeded', ['student.first_name', 'student.last_name'], true],
            ['health.sick_bay_admission_serious', ['student.first_name', 'student.last_name', 'severity'], true],
            ['health.outbreak_threshold_reached', ['presenting_complaint', 'case_count'], true],
            ['health.incident_guardian_notified', ['student.first_name', 'student.last_name', 'incident_type'], false],
            ['health.serious_incident_head_notified', ['incident_type', 'severity'], true],
            ['health.external_referral_made', ['student.first_name', 'student.last_name', 'facility_name'], false],
            ['behaviour.record_guardian_notified', ['student.first_name', 'student.last_name', 'category', 'polarity'], false],
            ['behaviour.sanction_issued', ['student.first_name', 'student.last_name', 'sanction_type'], false],
            ['safeguarding.immediate_risk_concern', ['concern_category'], true],
            ['safeguarding.vendor_access_attempt', ['case_reference'], true],
            ['safeguarding.break_glass_used', ['case_reference'], true],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['sms', 'email'],
                defaultAudience: 'guardian_or_staff',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book G BRD-06 §7/BRD-07 §7.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['health.notify_guardian_on_admission', 'string', 'moderate_and_above', 'Minimum admission severity that triggers a guardian notification.'],
            ['health.serious_admission_bypasses_quiet_hours', 'bool', '1', 'Whether a serious/emergency admission notification bypasses quiet hours (BR-BRD-06-016).'],
            ['health.require_care_plan_severity', 'string', 'severe', 'Minimum condition severity that requires an emergency care plan before term begins.'],
            ['health.controlled_requires_witness', 'bool', '1', 'Whether controlled medication requires a second witness (BR-BRD-06-013).'],
            ['health.outbreak_threshold_cases', 'int', '5', 'Admissions with the same presenting complaint, within the window, that trigger an outbreak alert.'],
            ['health.outbreak_window_days', 'int', '3', 'Rolling window, in days, for outbreak clustering.'],
            ['health.stock_expiry_alert_days', 'json', '[90,30,7]', 'Days before expiry at which clinic stock alerts.'],
            ['health.learner_portal_shows_conditions', 'bool', '1', 'Whether a learner sees their own allergies/immunisations/medication in the portal.'],
            ['health.learner_portal_excludes_mental_health', 'bool', '1', 'Whether mental-health condition entries are excluded from the learner portal view (locked true in spirit).'],
            ['health.nurse_staff_id', 'int', '', 'Staff record for the school nurse, notified on outbreak threshold.'],
            ['health.head_staff_id', 'int', '', 'Staff record for the head, notified on serious incidents and outbreak threshold.'],
            ['behaviour.automatic_sanctioning_enabled', 'bool', '0', 'Whether a trigger rule may be created as automatic — enabling this is itself a logged setting change.'],
            ['behaviour.automatic_sanction_issuer_user_id', 'int', '', 'The named person an automatic trigger rule issues its sanction as (BR-BRD-07-004) — automatic rules suggest-only until this is set.'],
            ['behaviour.sanction_approval_from_severity', 'int', '3', 'Sanction severity level, and above, that requires approval before taking effect.'],
            ['behaviour.appeal_window_days', 'int', '5', 'Default appeal window in days.'],
            ['behaviour.appeal_suspends_sanction', 'bool', '1', 'Whether a lodged appeal suspends the sanction pending decision.'],
            ['behaviour.conduct_grade_bands', 'json', '[{"min":10,"grade":"Excellent"},{"min":0,"grade":"Good"},{"min":-10,"grade":"Fair"},{"min":-9999,"grade":"Poor"}]', 'Net-points thresholds mapped to a conduct grade, evaluated highest first.'],
            ['behaviour.notify_guardian_from_severity', 'int', '2', 'Category severity level, and above, that notifies the guardian automatically.'],
            ['behaviour.show_conduct_on_report_card', 'bool', '1', 'Whether the computed conduct grade appears on the report card.'],
            ['behaviour.merit_visible_to_learner', 'bool', '1', 'Whether a learner sees their own merit points in the portal.'],
            ['safeguarding.lead_staff_id', 'int', '', 'Staff record for the designated safeguarding lead — required at setup.'],
            ['safeguarding.deputy_lead_staff_id', 'int', '', 'Staff record for the designated deputy safeguarding lead — required at setup.'],
            ['safeguarding.immediate_risk_bypasses_quiet_hours', 'bool', '1', 'Whether an immediate-risk concern bypasses quiet hours (locked).'],
            ['safeguarding.default_grant_expiry_days', 'int', '30', 'Default expiry, in days, for a new per-case access grant.'],
            ['safeguarding.anonymous_reporting_enabled', 'bool', '1', 'Whether anonymous reporting is available (cannot be disabled).'],
            ['safeguarding.learner_report_entry_point_visible', 'bool', '1', '"Tell someone" learner entry point visibility (locked).'],
            ['safeguarding.retention_years_after_exit', 'int', '25', 'Years a closed case is retained after the learner exits the school.'],
            ['safeguarding.review_frequency_days', 'int', '30', 'Default review frequency, in days, for the vulnerable learner register.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'BRD',
                'group_key' => explode('.', $key)[0],
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
        TenantModelRegistry::register(MedicalRecord::class, fn (School $school): MedicalRecord => MedicalRecord::factory()->for($school)->create());

        TenantModelRegistry::register(MedicalCondition::class, fn (School $school): MedicalCondition => MedicalCondition::factory()->for($school)->create());

        TenantModelRegistry::register(EmergencyCarePlan::class, function (School $school): EmergencyCarePlan {
            $student = Student::factory()->for($school)->create();
            $condition = MedicalCondition::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);

            return EmergencyCarePlan::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'condition_id' => $condition->id]);
        });

        TenantModelRegistry::register(Immunisation::class, fn (School $school): Immunisation => Immunisation::factory()->for($school)->create());

        TenantModelRegistry::register(SickBayAdmission::class, function (School $school): SickBayAdmission {
            [$student, $term] = $this->studentAndTerm($school);

            return SickBayAdmission::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'student_id' => $student->id, 'admitted_by' => User::factory()]);
        });

        TenantModelRegistry::register(ClinicObservation::class, function (School $school): ClinicObservation {
            $admission = SickBayAdmission::factory()->create(['school_id' => $school->id, 'admitted_by' => User::factory()]);

            return ClinicObservation::factory()->create(['school_id' => $school->id, 'admission_id' => $admission->id, 'observed_by' => User::factory()]);
        });

        TenantModelRegistry::register(Consultation::class, fn (School $school): Consultation => Consultation::factory()->for($school)->create());

        TenantModelRegistry::register(MedicalConsent::class, function (School $school): MedicalConsent {
            $student = Student::factory()->for($school)->create();

            return MedicalConsent::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'guardian_id' => Guardian::factory()->for($school)]);
        });

        TenantModelRegistry::register(Prescription::class, fn (School $school): Prescription => Prescription::factory()->for($school)->create());

        TenantModelRegistry::register(MedicationAdministration::class, function (School $school): MedicationAdministration {
            $student = Student::factory()->for($school)->create();

            return MedicationAdministration::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'administered_by' => User::factory()]);
        });

        TenantModelRegistry::register(HealthIncident::class, function (School $school): HealthIncident {
            [, $term] = $this->studentAndTerm($school);

            return HealthIncident::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'reported_by' => User::factory()]);
        });

        TenantModelRegistry::register(ExternalReferral::class, function (School $school): ExternalReferral {
            $student = Student::factory()->for($school)->create();

            return ExternalReferral::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'referred_by' => User::factory()]);
        });

        TenantModelRegistry::register(ClinicStock::class, fn (School $school): ClinicStock => ClinicStock::factory()->for($school)->create());

        TenantModelRegistry::register(ControlledStockLogEntry::class, function (School $school): ControlledStockLogEntry {
            $stock = ClinicStock::factory()->create(['school_id' => $school->id, 'is_controlled' => true]);

            return ControlledStockLogEntry::factory()->create([
                'school_id' => $school->id, 'clinic_stock_id' => $stock->id,
                'performed_by' => User::factory(), 'witnessed_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(HealthScreening::class, function (School $school): HealthScreening {
            [, $term] = $this->studentAndTerm($school);

            return HealthScreening::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);
        });

        TenantModelRegistry::register(SanctionType::class, fn (School $school): SanctionType => SanctionType::factory()->for($school)->create());

        TenantModelRegistry::register(BehaviourCategory::class, fn (School $school): BehaviourCategory => BehaviourCategory::factory()->for($school)->create());

        TenantModelRegistry::register(BehaviourRecord::class, function (School $school): BehaviourRecord {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $category = BehaviourCategory::factory()->create(['school_id' => $school->id]);

            return BehaviourRecord::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'student_id' => $student->id, 'category_id' => $category->id, 'reported_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(BehaviourPointBalance::class, function (School $school): BehaviourPointBalance {
            [$student, $term] = $this->studentAndTerm($school);

            return BehaviourPointBalance::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'term_id' => $term->id]);
        });

        TenantModelRegistry::register(BehaviourTriggerRule::class, function (School $school): BehaviourTriggerRule {
            $sanctionType = SanctionType::factory()->create(['school_id' => $school->id]);

            return BehaviourTriggerRule::factory()->create(['school_id' => $school->id, 'suggested_sanction_id' => $sanctionType->id]);
        });

        TenantModelRegistry::register(Sanction::class, function (School $school): Sanction {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $sanctionType = SanctionType::factory()->create(['school_id' => $school->id]);

            return Sanction::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'student_id' => $student->id, 'sanction_type_id' => $sanctionType->id, 'issued_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(DisciplinaryCommittee::class, function (School $school): DisciplinaryCommittee {
            $student = Student::factory()->for($school)->create();

            return DisciplinaryCommittee::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'chaired_by' => User::factory()]);
        });

        TenantModelRegistry::register(Appeal::class, function (School $school): Appeal {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $sanctionType = SanctionType::factory()->create(['school_id' => $school->id]);
            $sanction = Sanction::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'student_id' => $student->id, 'sanction_type_id' => $sanctionType->id, 'issued_by' => User::factory(),
            ]);

            return Appeal::factory()->create(['school_id' => $school->id, 'sanction_id' => $sanction->id]);
        });

        TenantModelRegistry::register(Detention::class, function (School $school): Detention {
            [$student, $term] = $this->studentAndTerm($school);

            return Detention::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'student_id' => $student->id]);
        });

        TenantModelRegistry::register(StudentLeadership::class, function (School $school): StudentLeadership {
            [$student, , $year] = $this->studentAndTerm($school);

            return StudentLeadership::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'student_id' => $student->id, 'appointed_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(SafeguardingCase::class, function (School $school): SafeguardingCase {
            $student = Student::factory()->for($school)->create();
            $lead = Staff::factory()->for($school)->create();

            return SafeguardingCase::factory()->create([
                'school_id' => $school->id, 'student_id' => $student->id, 'lead_staff_id' => $lead->id, 'opened_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(SafeguardingConcern::class, fn (School $school): SafeguardingConcern => SafeguardingConcern::factory()->for($school)->create());

        TenantModelRegistry::register(CaseAccessGrant::class, function (School $school): CaseAccessGrant {
            $case = SafeguardingCase::factory()->create([
                'school_id' => $school->id, 'student_id' => Student::factory()->for($school),
                'lead_staff_id' => Staff::factory()->for($school), 'opened_by' => User::factory(),
            ]);

            return CaseAccessGrant::factory()->create(['school_id' => $school->id, 'case_id' => $case->id, 'user_id' => User::factory(), 'granted_by' => User::factory()]);
        });

        TenantModelRegistry::register(CaseEntry::class, function (School $school): CaseEntry {
            $case = SafeguardingCase::factory()->create([
                'school_id' => $school->id, 'student_id' => Student::factory()->for($school),
                'lead_staff_id' => Staff::factory()->for($school), 'opened_by' => User::factory(),
            ]);

            return CaseEntry::factory()->create(['school_id' => $school->id, 'case_id' => $case->id, 'recorded_by' => User::factory()]);
        });

        TenantModelRegistry::register(CounsellingSession::class, function (School $school): CounsellingSession {
            $student = Student::factory()->for($school)->create();
            $counsellor = Staff::factory()->for($school)->create();

            return CounsellingSession::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'counsellor_staff_id' => $counsellor->id]);
        });

        TenantModelRegistry::register(RiskAssessment::class, function (School $school): RiskAssessment {
            $case = SafeguardingCase::factory()->create([
                'school_id' => $school->id, 'student_id' => Student::factory()->for($school),
                'lead_staff_id' => Staff::factory()->for($school), 'opened_by' => User::factory(),
            ]);

            return RiskAssessment::factory()->create(['school_id' => $school->id, 'case_id' => $case->id, 'assessed_by' => User::factory()]);
        });

        TenantModelRegistry::register(AgencyReferral::class, function (School $school): AgencyReferral {
            $case = SafeguardingCase::factory()->create([
                'school_id' => $school->id, 'student_id' => Student::factory()->for($school),
                'lead_staff_id' => Staff::factory()->for($school), 'opened_by' => User::factory(),
            ]);

            return AgencyReferral::factory()->create(['school_id' => $school->id, 'case_id' => $case->id, 'referred_by' => User::factory()]);
        });

        TenantModelRegistry::register(VulnerableLearnerRegistration::class, function (School $school): VulnerableLearnerRegistration {
            $student = Student::factory()->for($school)->create();

            return VulnerableLearnerRegistration::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'identified_by' => User::factory()]);
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
