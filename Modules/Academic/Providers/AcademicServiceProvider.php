<?php

declare(strict_types=1);

namespace Modules\Academic\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Academic\Domain\Listeners\AutoCreateProjectOnLateEnrolmentListener;
use Modules\Academic\Domain\Listeners\CreateSubstitutionsForApprovedLeaveListener;
use Modules\Academic\Domain\Listeners\ExemptProjectOnSubjectDropListener;
use Modules\Academic\Domain\Support\ContinuousAssessmentProvider;
use Modules\Academic\Domain\Support\EloquentContinuousAssessmentProvider;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\AssessmentMarkVersion;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\Academic\Models\AttendanceMarkingCompliance;
use Modules\Academic\Models\AttendanceReasonCode;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Academic\Models\BulkTextbookIssue;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtResponse;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\CommentBank;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\DiscussionPost;
use Modules\Academic\Models\DiscussionThread;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSeating;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\ExamSlotPlan;
use Modules\Academic\Models\GradeBand;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\InvigilationAssignment;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerProjectMilestone;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\LegacyCalaRecord;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryItem;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Academic\Models\Loan;
use Modules\Academic\Models\MalpracticeIncident;
use Modules\Academic\Models\Pathway;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectEvidence;
use Modules\Academic\Models\ProjectMarkVersion;
use Modules\Academic\Models\ProjectMilestone;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Academic\Models\ReportCardRun;
use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\ScriptCustodyLogEntry;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Academic\Models\SubjectGroup;
use Modules\Academic\Models\SubjectPrerequisite;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Academic\Models\Syllabus;
use Modules\Academic\Models\TeachingGroup;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableConstraint;
use Modules\Academic\Models\TimetableException;
use Modules\Academic\Models\TimetableGenerationRun;
use Modules\Academic\Models\TimetableSlot;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Domain\Events\LeaveApproved;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Domain C Academic Core. ACA-01 is now fully built (`pathways`,
 * `level_subject_offerings`, `subject_prerequisites`, and `syllabi`
 * joined the original curriculum/selection-rule tables). ACA-02 is now
 * fully built too: its billing-critical pair
 * (`learner_subject_enrolments`, `subject_enrolment_changes`) fires end
 * to end into `FIN-02` via `RaiseMidTermSubjectChangeBillingListener`
 * (see `Modules\Finance\Providers\FinanceServiceProvider::registerEventListeners()`),
 * and `class_allocations`/`teaching_groups`/`subject_selection_submissions`
 * joined the module in this pass. ACA-04's core marking/amend/summary/
 * compliance engine joined in this pass too — see its own tables'
 * migrations for the deliberate scope boundary (no MoPSE statutory
 * export, no hardware device drivers, no ACA-03/INT-03/BRD-02
 * integration yet).
 *
 * ACA-05's grading/results engine also joined this pass: grading
 * scales with contiguity-validated bands, assessments, marks with
 * append-only versioning, and the full `ComputeTermSubjectResultsAction`
 * -> `RecomputeSubjectPositionsAction` -> `ComputeTermResultsAction` ->
 * `RecomputeTermPositionsAction` aggregation pipeline, including the
 * whole-class/level recompute cascade `AmendMarkAction` triggers.
 * Deliberately deferred: report card document generation/publication
 * (Core's `GenerateDocumentAction` is real and callable but not yet
 * wired here), the `finance.report_gate_enabled` withholding check
 * (needs a new GL-based balance query — no existing one filters by
 * `fee_components.counts_toward_report_gate`), the actual CORE-07
 * `Approvable` adapter for a published-mark amendment (`AmendMarkAction`
 * takes the caller's word via `$data->approved`, mirroring
 * `AllocateTeacherAction::$overrideCeiling`), and SBP/`ACA-06`
 * continuous-assessment integration (Book E, not built).
 *
 * ACA-03's timetabling engine joined in this pass: period structures/
 * slots/venues, `TimetableClashDetector`'s four hard-clash levels
 * (teacher, venue, class, learner), a real (deliberately simplified —
 * see `GenerateTimetableAction`'s own docblock) greedy-generation
 * engine, publication, and the `AttendanceSessionGenerator` interface
 * closing into `ACA-04` via `GenerateAttendanceSessionsFromTimetableAction`.
 * Substitution/cover wires `Modules\People\Domain\Events\LeaveApproved`
 * for the first time anywhere in the codebase. Deliberately deferred:
 * teaching-group (set) requirement derivation (`TeachingGroup` has no
 * `periods_per_week` column yet), the "same department" cover tier
 * and deputy-head fallback, and `section`/`level`-scoped timetable
 * exceptions in session generation.
 *
 * ACA-06's SBP/legacy-CALA engine joined in this pass: the abstract
 * `AssessmentInstrument`, rubric-and-criteria authoring with a
 * weight-sum-to-100% guard, the full brief lifecycle (draft → HOD
 * approval → issue, creating a `learner_project` for every currently
 * enrolled learner), milestone/final evidence submission, criterion
 * marking, moderation (both marks stay visible), HOD verification,
 * and the append-only `project_mark_versions` history. `legacy_cala_records`
 * is read-only at the model level (DB-grant revocation is a deployment
 * step, mirroring `FinancialAuditLogEntry`). `SubjectEnrolmentAdded`/
 * `SubjectEnrolmentDropped` now also drive `AutoCreateProjectOnLateEnrolmentListener`
 * and `ExemptProjectOnSubjectDropListener`. The ⭐ `ContinuousAssessmentProvider`
 * interface is bound to `EloquentContinuousAssessmentProvider` and
 * `ComputeTermSubjectResultsAction` (ACA-05) now calls it for the
 * continuous-assessment branch it previously left permanently null —
 * see that action's own updated docblock for the weight-split rule
 * used where the spec is silent. Deliberately deferred: pro-rated
 * deadlines for late enrolment (BR-ACA-06-005's "where configured"
 * clause — no settings key exists yet), moderation sampling policy
 * automation (BR-ACA-06-012's percentage/fixed-count/boundary-case
 * sampler — moderation here is always a manual per-project action),
 * and evidence virus-scanning (`CORE-10`, not built).
 *
 * ACA-07's examinations engine joined in this pass: sessions, papers,
 * candidate derivation from `ACA-02` enrolments with gapless
 * `IndexNumberAllocator`-issued index numbers, setter/vetter
 * separation with a one-way seal, the time-locked, access-logged
 * `ReleaseExaminationPaperAction` (no override path for any caller —
 * `AC-ACA-07-001`), seating with separate-room handling, invigilation
 * with subject-teacher exclusion writing back to `PPL-04`'s duty
 * roster, append-only script custody with immediate discrepancy
 * detection, blind double marking with variance-routed third marking,
 * moderation, special arrangements, malpractice outcomes that void
 * marks without deleting them, and results processing that feeds
 * `ACA-05` as an `examination`-category assessment rather than a
 * second parallel result system. Deliberately deferred — real
 * infrastructure this pass does not build: per-session paper
 * encryption at rest and visible watermarking (`CORE-10` capabilities
 * that do not exist yet — see `ReleaseExaminationPaperAction`'s own
 * docblock), `FIN-02` entry-fee billing, the `CMP-01` candidate-set
 * export interface, moderation sampling policy automation (manual
 * per-candidate here, mirroring `ACA-06`), and confidentiality
 * field-level enforcement for `malpractice_incidents` (no screens
 * built yet in this pass to enforce it against).
 */
class AcademicServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Academic';

    protected string $nameLower = 'academic';

    public function register(): void
    {
        parent::register();

        $this->app->bind(ContinuousAssessmentProvider::class, EloquentContinuousAssessmentProvider::class);
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
     * Book E ACA-03 §6/BR-ACA-03-016. Closes the wiring
     * `Modules\People\Domain\Events\LeaveApproved`'s own docblock
     * named as pending.
     */
    private function registerEventListeners(): void
    {
        Event::listen(LeaveApproved::class, CreateSubstitutionsForApprovedLeaveListener::class);
        Event::listen(SubjectEnrolmentAdded::class, AutoCreateProjectOnLateEnrolmentListener::class);
        Event::listen(SubjectEnrolmentDropped::class, ExemptProjectOnSubjectDropListener::class);
    }

    /**
     * Book D ACA-04 §7 ⭐/BR-ACA-04-006. The first production
     * registration against Book A CORE-09's notification bus anywhere
     * in the codebase.
     */
    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'attendance.unexplained_absence',
            variables: ['guardian.name', 'student.first_name', 'student.last_name', 'date'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'primary_contact',
            isUrgent: false,
            isTransactional: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'timetable.cover_assigned',
            variables: ['class.name', 'subject.name', 'venue.name', 'work_set'],
            defaultChannels: ['email'],
            defaultAudience: 'cover_staff',
            isUrgent: false,
            isTransactional: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'lms.non_submission_reminder',
            variables: ['assignment.title', 'assignment.due_at'],
            defaultChannels: ['in_app'],
            defaultAudience: 'student',
            isUrgent: false,
            isTransactional: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'library.overdue_reminder',
            variables: ['item.title', 'loan.due_on'],
            defaultChannels: ['in_app'],
            defaultAudience: 'borrower',
            isUrgent: false,
            isTransactional: true,
        ));
    }

    /**
     * Book D ACA-02 §8 (subset — teaching-group and guardian-approval
     * settings are deferred alongside the tables they configure).
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['academic.subject_change_cutoff_week', 'int', '3', 'Week of term after which a subject add/drop requires approval.'],
            ['academic.allow_backdated_enrolment', 'bool', '1', 'Whether a subject add/drop may be backdated within the current term.'],
            ['academic.backdate_limit_days', 'int', '30', 'Furthest a subject enrolment change may be backdated.'],
            ['curriculum.default_framework', 'string', 'HBC_2024', 'Default curriculum framework code for a new subject/offering.'],
            ['curriculum.pathway_applies_from_ordinal', 'int', '8', 'Grade-level ordinal (Form 1) from which pathway assignment applies (BR-ACA-01-013).'],
            ['curriculum.enforce_prerequisites', 'bool', '0', 'Whether a missing subject prerequisite blocks selection rather than only warning.'],
            // Also covers ACA-02's academic.auto_enrol_compulsory_on_allocation
            // (BR-ACA-02-004) — the spec names the same toggle twice, once per
            // module; kept as one setting rather than two governing one behaviour.
            ['curriculum.auto_enrol_compulsory', 'bool', '1', 'Whether compulsory subjects auto-enrol on class allocation (BR-ACA-01-010/BR-ACA-02-004).'],
            ['curriculum.warn_unconfirmed_rules', 'bool', '1', 'Whether a persistent banner shows for selection rules flagged requires_confirmation.'],
            ['academic.enforce_teaching_group_capacity', 'bool', '0', 'Whether exceeding a teaching group\'s capacity refuses the assignment rather than only warning (BR-ACA-02-012).'],
            ['academic.require_guardian_subject_approval', 'bool', '1', 'Whether a subject selection submission needs guardian approval before school approval (BR-ACA-02-015).'],
            ['academic.show_indicative_fee_on_selection', 'bool', '1', 'Whether the subject selection form shows the indicative termly fee before submission (BR-ACA-02-016).'],
            ['attendance.default_mode_primary', 'string', 'daily', 'Default attendance marking mode for primary sections.'],
            ['attendance.default_mode_secondary', 'string', 'period', 'Default attendance marking mode for secondary sections.'],
            ['attendance.registration_cutoff_time', 'string', '08:15', 'Time of day after which a daily register mark counts as late (BR-ACA-04-014).'],
            ['attendance.notification_delay_minutes', 'int', '30', 'Minutes after an unexplained absence within which the primary contact is notified (BR-ACA-04-006).'],
            ['attendance.notify_on_unexplained_only', 'bool', '1', 'Whether the absence notification fires only for unexplained absences.'],
            ['attendance.lock_after_hours', 'int', '48', 'Hours after which an attendance session locks against amendment without academic.attendance.amend_locked (BR-ACA-04-009).'],
            ['attendance.chronic_threshold_days', 'int', '3', 'Consecutive unexplained absence days that escalates to the class teacher (BR-ACA-04-012).'],
            ['attendance.chronic_percent_threshold', 'int', '80', 'Attendance percentage below which a learner is flagged a chronic absentee (BR-ACA-04-013).'],
            ['attendance.show_percentage_on_report_card', 'bool', '1', 'Whether the attendance percentage appears on the report card.'],
            ['academic.position_basis', 'string', 'average_percent', 'Basis for class/level position ranking.'],
            ['academic.position_tiebreak', 'string', 'total_marks', 'Tie-break basis for position ranking.'],
            ['academic.show_positions_on_report', 'bool', '1', 'Whether class/level positions appear on the report card.'],
            ['academic.show_class_average_on_report', 'bool', '1', 'Whether the class average appears on the report card.'],
            ['academic.absent_counts_as_zero', 'bool', '0', 'Whether an absent-with-reason mark counts as zero rather than being excluded from the mean (BR-ACA-05-008).'],
            ['academic.require_moderation', 'bool', '0', 'Whether an assessment requires moderation sign-off before publication.'],
            ['academic.mark_entry_deadline_days_after_term', 'int', '7', 'Days after term end by which marks must be submitted.'],
            ['academic.publish_to_learner_portal', 'bool', '1', 'Whether a published result is also visible to the learner, not just the guardian.'],
            ['academic.promotion_min_average', 'int', '40', 'Minimum average percent for a promote recommendation.'],
            ['academic.promotion_min_subjects_passed', 'int', '5', 'Minimum subjects passed for a promote recommendation.'],
            ['academic.promotion_min_attendance', 'int', '75', 'Minimum attendance percent for a promote recommendation.'],
            ['timetable.default_cycle_type', 'string', 'weekly', 'Default cycle type for a new period structure.'],
            ['timetable.generation_time_budget_seconds', 'int', '300', 'Time budget for a generation run.'],
            ['timetable.max_consecutive_periods', 'int', '3', 'Maximum consecutive teaching periods for one teacher.'],
            ['timetable.allow_soft_violations_on_publish', 'bool', '1', 'Whether a soft constraint violation still allows publication.'],
            ['timetable.session_generation_days_ahead', 'int', '7', 'Days ahead attendance sessions are generated on publication.'],
            ['timetable.cover_fairness_balancing', 'bool', '1', 'Whether cover suggestion ranks by how many substitutions each candidate has already covered.'],
            ['timetable.notify_on_schedule_change', 'bool', '1', 'Whether affected staff/learners are notified of a schedule change.'],
            ['timetable.games_afternoon_days', 'string', '3', 'Comma-separated cycle days reserved for games in the afternoon.'],
            ['sbp.late_submission_policy', 'string', 'permit_and_flag', 'Whether evidence submitted after a milestone/final deadline is blocked or permitted-and-flagged as late (BR-ACA-06-009).'],
            ['sbp.moderation_sample_percent', 'int', '20', 'Percentage of marked projects sampled for moderation (BR-ACA-06-012).'],
            ['exams.seating_spacing_seats', 'int', '1', 'Physical seats skipped between consecutively seated candidates for the same paper (BR-ACA-07-006).'],
            ['exams.double_marking_enabled', 'bool', '0', 'Whether a second, blind mark is required before a paper mark settles (BR-ACA-07-013).'],
            ['exams.double_marking_variance_threshold', 'int', '5', 'Mark variance between first and second marker beyond which a candidate routes to third marking.'],
            ['exams.moderation_sample_percent', 'int', '15', 'Percentage of exam marks sampled for moderation (BR-ACA-07-014).'],
            ['exams.paper_max_downloads_per_user', 'int', '3', 'Downloads of a released paper per user before a security event is raised.'],
            ['exams.exclude_subject_teacher_from_invigilation', 'bool', '1', 'Whether a teacher of the examined subject is excluded from invigilating that paper by default (BR-ACA-07-010).'],
            ['academic.lms_similarity_threshold_percent', 'int', '70', 'Word-shingle similarity percentage above which two assignment submissions in the same class are flagged for teacher review (BR-ACA-08-006).'],
            ['cbt.autosave_interval_seconds', 'int', '15', 'Maximum seconds between client autosaves during a CBT attempt (BR-ACA-09-001).'],
            ['cbt.default_max_tab_switches', 'int', '3', 'Default tab-switch limit before a CBT attempt is flagged for review when a test does not set its own.'],
            ['cbt.auto_submit_on_time_expiry', 'bool', '1', 'Whether a CBT attempt auto-submits when its time limit is reached (locked — always true in this pass).'],
            ['library.daily_fine_rate_minor', 'int', '50', 'Daily fine rate (minor units) for a late library return, capped at the item\'s replacement cost (BR-ACA-10-006).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'ACA',
                'group_key' => 'academic',
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
        TenantModelRegistry::register(CurriculumFramework::class, fn (School $school): CurriculumFramework => CurriculumFramework::factory()->for($school)->create());

        TenantModelRegistry::register(SubjectGroup::class, fn (School $school): SubjectGroup => SubjectGroup::factory()->for($school)->create());

        TenantModelRegistry::register(Subject::class, function (School $school): Subject {
            $framework = CurriculumFramework::factory()->for($school)->create();

            return Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
        });

        TenantModelRegistry::register(SubjectSelectionRule::class, function (School $school): SubjectSelectionRule {
            $framework = CurriculumFramework::factory()->for($school)->create();

            return SubjectSelectionRule::factory()->for($school)->create(['framework_id' => $framework->id]);
        });

        TenantModelRegistry::register(LearnerSubjectEnrolment::class, function (School $school): LearnerSubjectEnrolment {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);

            return LearnerSubjectEnrolment::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'added_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(SubjectEnrolmentChange::class, function (School $school): SubjectEnrolmentChange {
            [$student, $term] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);

            return SubjectEnrolmentChange::factory()->create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'changed_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(Pathway::class, function (School $school): Pathway {
            $framework = CurriculumFramework::factory()->for($school)->create();

            return Pathway::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
        });

        TenantModelRegistry::register(LevelSubjectOffering::class, function (School $school): LevelSubjectOffering {
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $year = AcademicYear::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();

            return LevelSubjectOffering::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'grade_level_id' => $gradeLevel->id,
                'subject_id' => $subject->id,
            ]);
        });

        TenantModelRegistry::register(SubjectPrerequisite::class, function (School $school): SubjectPrerequisite {
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $prerequisite = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);

            return SubjectPrerequisite::factory()->create([
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'prerequisite_subject_id' => $prerequisite->id,
            ]);
        });

        TenantModelRegistry::register(Syllabus::class, function (School $school): Syllabus {
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);

            return Syllabus::factory()->create([
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'framework_id' => $framework->id,
            ]);
        });

        TenantModelRegistry::register(ClassAllocation::class, function (School $school): ClassAllocation {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $class = SchoolClass::factory()->for($school)->create();

            return ClassAllocation::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'allocated_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(TeachingGroup::class, function (School $school): TeachingGroup {
            [, $term, $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();

            return TeachingGroup::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
        });

        TenantModelRegistry::register(TeachingGroupMember::class, function (School $school): TeachingGroupMember {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $group = TeachingGroup::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'grade_level_id' => $gradeLevel->id,
            ]);

            return TeachingGroupMember::factory()->create([
                'school_id' => $school->id,
                'teaching_group_id' => $group->id,
                'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(SubjectSelectionSubmission::class, function (School $school): SubjectSelectionSubmission {
            $student = Student::factory()->for($school)->create();
            $year = AcademicYear::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();

            return SubjectSelectionSubmission::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'student_id' => $student->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
        });

        TenantModelRegistry::register(AttendanceReasonCode::class, fn (School $school): AttendanceReasonCode => AttendanceReasonCode::factory()->for($school)->create());

        TenantModelRegistry::register(AttendanceSession::class, function (School $school): AttendanceSession {
            [, $term, $year] = $this->studentAndTerm($school);
            $class = SchoolClass::factory()->for($school)->create();

            return AttendanceSession::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'class_id' => $class->id,
            ]);
        });

        TenantModelRegistry::register(AttendanceRecord::class, function (School $school): AttendanceRecord {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $class = SchoolClass::factory()->for($school)->create();
            $session = AttendanceSession::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'class_id' => $class->id,
            ]);

            return AttendanceRecord::factory()->create([
                'school_id' => $school->id,
                'session_id' => $session->id,
                'student_id' => $student->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(AttendanceMarkingCompliance::class, function (School $school): AttendanceMarkingCompliance {
            [, $term] = $this->studentAndTerm($school);
            $staff = Staff::factory()->for($school)->create();

            return AttendanceMarkingCompliance::factory()->create([
                'school_id' => $school->id,
                'term_id' => $term->id,
                'staff_id' => $staff->id,
            ]);
        });

        // AttendanceSummary is deliberately absent — a cache table
        // rebuilt only by RebuildAttendanceSummaryAction, same reasoning
        // as Book A's CORE-08/12 infrastructure tables.

        TenantModelRegistry::register(GradingScale::class, fn (School $school): GradingScale => GradingScale::factory()->for($school)->create());

        TenantModelRegistry::register(GradeBand::class, function (School $school): GradeBand {
            $scale = GradingScale::factory()->for($school)->create();

            return GradeBand::factory()->create(['school_id' => $school->id, 'grading_scale_id' => $scale->id]);
        });

        TenantModelRegistry::register(AssessmentType::class, fn (School $school): AssessmentType => AssessmentType::factory()->for($school)->create());

        TenantModelRegistry::register(Assessment::class, function (School $school): Assessment {
            [, $term, $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $type = AssessmentType::factory()->for($school)->create();

            return Assessment::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'assessment_type_id' => $type->id,
                'subject_id' => $subject->id,
                'created_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(AssessmentMark::class, function (School $school): AssessmentMark {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $type = AssessmentType::factory()->for($school)->create();
            $assessment = Assessment::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'assessment_type_id' => $type->id,
                'subject_id' => $subject->id,
                'created_by' => User::factory(),
            ]);

            return AssessmentMark::factory()->create([
                'school_id' => $school->id,
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'term_id' => $term->id,
                'entered_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(AssessmentMarkVersion::class, function (School $school): AssessmentMarkVersion {
            [$student] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $type = AssessmentType::factory()->for($school)->create();
            $assessment = Assessment::factory()->create([
                'school_id' => $school->id,
                'assessment_type_id' => $type->id,
                'subject_id' => $subject->id,
                'created_by' => User::factory(),
            ]);

            return AssessmentMarkVersion::factory()->create([
                'school_id' => $school->id,
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'changed_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(TermSubjectResult::class, function (School $school): TermSubjectResult {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);

            return TermSubjectResult::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'subject_id' => $subject->id,
            ]);
        });

        TenantModelRegistry::register(TermResult::class, function (School $school): TermResult {
            [$student, $term, $year] = $this->studentAndTerm($school);
            $class = SchoolClass::factory()->for($school)->create();

            return TermResult::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'class_id' => $class->id,
            ]);
        });

        TenantModelRegistry::register(CommentBank::class, fn (School $school): CommentBank => CommentBank::factory()->for($school)->create(['created_by' => User::factory()]));

        TenantModelRegistry::register(ReportCardRun::class, function (School $school): ReportCardRun {
            [, $term] = $this->studentAndTerm($school);

            return ReportCardRun::factory()->create([
                'school_id' => $school->id,
                'term_id' => $term->id,
                'requested_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(PeriodStructure::class, fn (School $school): PeriodStructure => PeriodStructure::factory()->for($school)->create());

        TenantModelRegistry::register(PeriodSlot::class, function (School $school): PeriodSlot {
            $structure = PeriodStructure::factory()->for($school)->create();

            return PeriodSlot::factory()->create(['school_id' => $school->id, 'structure_id' => $structure->id]);
        });

        TenantModelRegistry::register(Venue::class, fn (School $school): Venue => Venue::factory()->for($school)->create());

        TenantModelRegistry::register(Timetable::class, function (School $school): Timetable {
            [, $term, $year] = $this->studentAndTerm($school);
            $structure = PeriodStructure::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);

            return Timetable::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'structure_id' => $structure->id,
                'created_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(TimetableSlot::class, function (School $school): TimetableSlot {
            [, $term, $year] = $this->studentAndTerm($school);
            $structure = PeriodStructure::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);
            $periodSlot = PeriodSlot::factory()->create(['school_id' => $school->id, 'structure_id' => $structure->id]);
            $timetable = Timetable::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'structure_id' => $structure->id,
                'created_by' => User::factory(),
            ]);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $staff = Staff::factory()->for($school)->create();

            return TimetableSlot::factory()->create([
                'school_id' => $school->id,
                'timetable_id' => $timetable->id,
                'term_id' => $term->id,
                'period_slot_id' => $periodSlot->id,
                'subject_id' => $subject->id,
                'staff_id' => $staff->id,
            ]);
        });

        TenantModelRegistry::register(TimetableConstraint::class, fn (School $school): TimetableConstraint => TimetableConstraint::factory()->for($school)->create());

        TenantModelRegistry::register(TimetableGenerationRun::class, function (School $school): TimetableGenerationRun {
            $timetable = Timetable::factory()->for($school)->create(['created_by' => User::factory()]);

            return TimetableGenerationRun::factory()->create([
                'school_id' => $school->id,
                'timetable_id' => $timetable->id,
                'requested_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(LessonSubstitution::class, function (School $school): LessonSubstitution {
            [, $term, $year] = $this->studentAndTerm($school);
            $structure = PeriodStructure::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);
            $periodSlot = PeriodSlot::factory()->create(['school_id' => $school->id, 'structure_id' => $structure->id]);
            $timetable = Timetable::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'structure_id' => $structure->id, 'created_by' => User::factory(),
            ]);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $staff = Staff::factory()->for($school)->create();
            $slot = TimetableSlot::factory()->create([
                'school_id' => $school->id, 'timetable_id' => $timetable->id, 'term_id' => $term->id,
                'period_slot_id' => $periodSlot->id, 'subject_id' => $subject->id, 'staff_id' => $staff->id,
            ]);

            return LessonSubstitution::factory()->create([
                'school_id' => $school->id,
                'term_id' => $term->id,
                'timetable_slot_id' => $slot->id,
                'absent_staff_id' => $staff->id,
            ]);
        });

        TenantModelRegistry::register(TimetableException::class, function (School $school): TimetableException {
            [, $term] = $this->studentAndTerm($school);

            return TimetableException::factory()->create([
                'school_id' => $school->id,
                'term_id' => $term->id,
                'created_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(ExamSlotPlan::class, function (School $school): ExamSlotPlan {
            [, $term, $year] = $this->studentAndTerm($school);

            return ExamSlotPlan::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'created_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(AssessmentInstrument::class, function (School $school): AssessmentInstrument {
            $framework = CurriculumFramework::factory()->for($school)->create();

            return AssessmentInstrument::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
        });

        TenantModelRegistry::register(ProjectRubric::class, fn (School $school): ProjectRubric => ProjectRubric::factory()->for($school)->create());

        TenantModelRegistry::register(ProjectBrief::class, function (School $school): ProjectBrief {
            [, , $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $instrument = AssessmentInstrument::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
            $rubric = ProjectRubric::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();

            return ProjectBrief::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'instrument_id' => $instrument->id,
                'subject_id' => $subject->id,
                'grade_level_id' => $gradeLevel->id,
                'rubric_id' => $rubric->id,
                'created_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(ProjectMilestone::class, function (School $school): ProjectMilestone {
            [, , $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $instrument = AssessmentInstrument::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
            $rubric = ProjectRubric::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $brief = ProjectBrief::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'instrument_id' => $instrument->id,
                'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id, 'rubric_id' => $rubric->id,
                'created_by' => User::factory(),
            ]);

            return ProjectMilestone::factory()->create(['school_id' => $school->id, 'brief_id' => $brief->id]);
        });

        TenantModelRegistry::register(LearnerProject::class, function (School $school): LearnerProject {
            [$student, , $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $instrument = AssessmentInstrument::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
            $rubric = ProjectRubric::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $brief = ProjectBrief::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'instrument_id' => $instrument->id,
                'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id, 'rubric_id' => $rubric->id,
                'created_by' => User::factory(),
            ]);

            return LearnerProject::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'brief_id' => $brief->id,
                'student_id' => $student->id,
                'subject_id' => $subject->id,
            ]);
        });

        TenantModelRegistry::register(LearnerProjectMilestone::class, function (School $school): LearnerProjectMilestone {
            [$student, , $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $instrument = AssessmentInstrument::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
            $rubric = ProjectRubric::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $brief = ProjectBrief::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'instrument_id' => $instrument->id,
                'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id, 'rubric_id' => $rubric->id,
                'created_by' => User::factory(),
            ]);
            $milestone = ProjectMilestone::factory()->create(['school_id' => $school->id, 'brief_id' => $brief->id]);
            $learnerProject = LearnerProject::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'brief_id' => $brief->id,
                'student_id' => $student->id, 'subject_id' => $subject->id,
            ]);

            return LearnerProjectMilestone::factory()->create([
                'school_id' => $school->id,
                'learner_project_id' => $learnerProject->id,
                'milestone_id' => $milestone->id,
            ]);
        });

        TenantModelRegistry::register(ProjectEvidence::class, function (School $school): ProjectEvidence {
            [$student, , $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $instrument = AssessmentInstrument::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
            $rubric = ProjectRubric::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $brief = ProjectBrief::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'instrument_id' => $instrument->id,
                'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id, 'rubric_id' => $rubric->id,
                'created_by' => User::factory(),
            ]);
            $learnerProject = LearnerProject::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'brief_id' => $brief->id,
                'student_id' => $student->id, 'subject_id' => $subject->id,
            ]);

            return ProjectEvidence::factory()->create([
                'school_id' => $school->id,
                'learner_project_id' => $learnerProject->id,
                'uploaded_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(ProjectMarkVersion::class, function (School $school): ProjectMarkVersion {
            [$student, , $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $instrument = AssessmentInstrument::factory()->create(['school_id' => $school->id, 'framework_id' => $framework->id]);
            $rubric = ProjectRubric::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $brief = ProjectBrief::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'instrument_id' => $instrument->id,
                'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id, 'rubric_id' => $rubric->id,
                'created_by' => User::factory(),
            ]);
            $learnerProject = LearnerProject::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'brief_id' => $brief->id,
                'student_id' => $student->id, 'subject_id' => $subject->id,
            ]);

            return ProjectMarkVersion::factory()->create([
                'school_id' => $school->id,
                'learner_project_id' => $learnerProject->id,
                'changed_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(LegacyCalaRecord::class, function (School $school): LegacyCalaRecord {
            [$student, , $year] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);

            return LegacyCalaRecord::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'student_id' => $student->id,
                'subject_id' => $subject->id,
            ]);
        });

        TenantModelRegistry::register(ExaminationSession::class, fn (School $school): ExaminationSession => ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]));

        TenantModelRegistry::register(ExaminationPaper::class, function (School $school): ExaminationPaper {
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);

            return ExaminationPaper::factory()->create([
                'school_id' => $school->id,
                'session_id' => $session->id,
                'subject_id' => $subject->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
        });

        TenantModelRegistry::register(ExaminationCandidate::class, function (School $school): ExaminationCandidate {
            [$student] = $this->studentAndTerm($school);
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);

            return ExaminationCandidate::factory()->create([
                'school_id' => $school->id,
                'session_id' => $session->id,
                'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(ExaminationSeating::class, function (School $school): ExaminationSeating {
            [$student] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);
            $paper = ExaminationPaper::factory()->create([
                'school_id' => $school->id, 'session_id' => $session->id, 'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id,
            ]);
            $candidate = ExaminationCandidate::factory()->create(['school_id' => $school->id, 'session_id' => $session->id, 'student_id' => $student->id]);
            $venue = Venue::factory()->for($school)->create();

            return ExaminationSeating::factory()->create([
                'school_id' => $school->id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id, 'venue_id' => $venue->id,
            ]);
        });

        TenantModelRegistry::register(InvigilationAssignment::class, function (School $school): InvigilationAssignment {
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);
            $paper = ExaminationPaper::factory()->create([
                'school_id' => $school->id, 'session_id' => $session->id, 'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id,
            ]);
            $venue = Venue::factory()->for($school)->create();
            $staff = Staff::factory()->for($school)->create();

            return InvigilationAssignment::factory()->create([
                'school_id' => $school->id, 'paper_id' => $paper->id, 'venue_id' => $venue->id, 'staff_id' => $staff->id,
            ]);
        });

        TenantModelRegistry::register(ScriptBatch::class, function (School $school): ScriptBatch {
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);
            $paper = ExaminationPaper::factory()->create([
                'school_id' => $school->id, 'session_id' => $session->id, 'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id,
            ]);

            return ScriptBatch::factory()->create(['school_id' => $school->id, 'paper_id' => $paper->id]);
        });

        TenantModelRegistry::register(ScriptCustodyLogEntry::class, function (School $school): ScriptCustodyLogEntry {
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);
            $paper = ExaminationPaper::factory()->create([
                'school_id' => $school->id, 'session_id' => $session->id, 'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id,
            ]);
            $batch = ScriptBatch::factory()->create(['school_id' => $school->id, 'paper_id' => $paper->id]);

            return ScriptCustodyLogEntry::factory()->create(['school_id' => $school->id, 'batch_id' => $batch->id, 'recorded_by' => User::factory()]);
        });

        TenantModelRegistry::register(ExaminationMark::class, function (School $school): ExaminationMark {
            [$student] = $this->studentAndTerm($school);
            $framework = CurriculumFramework::factory()->for($school)->create();
            $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);
            $paper = ExaminationPaper::factory()->create([
                'school_id' => $school->id, 'session_id' => $session->id, 'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id,
            ]);
            $candidate = ExaminationCandidate::factory()->create(['school_id' => $school->id, 'session_id' => $session->id, 'student_id' => $student->id]);

            return ExaminationMark::factory()->create([
                'school_id' => $school->id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id, 'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(SpecialArrangement::class, function (School $school): SpecialArrangement {
            [$student] = $this->studentAndTerm($school);
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);

            return SpecialArrangement::factory()->create(['school_id' => $school->id, 'session_id' => $session->id, 'student_id' => $student->id]);
        });

        TenantModelRegistry::register(MalpracticeIncident::class, function (School $school): MalpracticeIncident {
            $session = ExaminationSession::factory()->for($school)->create(['created_by' => User::factory()]);

            return MalpracticeIncident::factory()->create(['school_id' => $school->id, 'session_id' => $session->id, 'reported_by' => User::factory()]);
        });

        TenantModelRegistry::register(CourseSpace::class, fn (School $school): CourseSpace => $this->courseSpaceFor($school));

        TenantModelRegistry::register(ContentItem::class, function (School $school): ContentItem {
            $courseSpace = $this->courseSpaceFor($school);

            return ContentItem::factory()->create(['school_id' => $school->id, 'course_space_id' => $courseSpace->id]);
        });

        TenantModelRegistry::register(Assignment::class, function (School $school): Assignment {
            $courseSpace = $this->courseSpaceFor($school);

            return Assignment::factory()->create(['school_id' => $school->id, 'course_space_id' => $courseSpace->id]);
        });

        TenantModelRegistry::register(AssignmentSubmission::class, function (School $school): AssignmentSubmission {
            $courseSpace = $this->courseSpaceFor($school);
            $assignment = Assignment::factory()->create(['school_id' => $school->id, 'course_space_id' => $courseSpace->id]);
            $student = Student::factory()->for($school)->create();

            return AssignmentSubmission::factory()->create([
                'school_id' => $school->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(DiscussionThread::class, function (School $school): DiscussionThread {
            $courseSpace = $this->courseSpaceFor($school);

            return DiscussionThread::factory()->create([
                'school_id' => $school->id, 'course_space_id' => $courseSpace->id, 'created_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(DiscussionPost::class, function (School $school): DiscussionPost {
            $courseSpace = $this->courseSpaceFor($school);
            $thread = DiscussionThread::factory()->create([
                'school_id' => $school->id, 'course_space_id' => $courseSpace->id, 'created_by' => User::factory(),
            ]);

            return DiscussionPost::factory()->create(['school_id' => $school->id, 'thread_id' => $thread->id, 'posted_by_id' => User::factory()]);
        });

        TenantModelRegistry::register(QuestionBankItem::class, function (School $school): QuestionBankItem {
            $subject = Subject::factory()->for($school)->create(['framework_id' => CurriculumFramework::factory()->for($school)->create()->id]);

            return QuestionBankItem::factory()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'created_by' => User::factory()]);
        });

        TenantModelRegistry::register(CbtTest::class, fn (School $school): CbtTest => $this->cbtTestFor($school));

        TenantModelRegistry::register(CbtCandidateAttempt::class, function (School $school): CbtCandidateAttempt {
            [$student] = $this->studentAndTerm($school);
            $test = $this->cbtTestFor($school);

            return CbtCandidateAttempt::factory()->create(['school_id' => $school->id, 'test_id' => $test->id, 'student_id' => $student->id]);
        });

        TenantModelRegistry::register(CbtResponse::class, function (School $school): CbtResponse {
            [$student] = $this->studentAndTerm($school);
            $test = $this->cbtTestFor($school);
            $attempt = CbtCandidateAttempt::factory()->create(['school_id' => $school->id, 'test_id' => $test->id, 'student_id' => $student->id]);
            $question = QuestionBankItem::factory()->create(['school_id' => $school->id, 'subject_id' => $test->subject_id, 'created_by' => User::factory()]);

            return CbtResponse::factory()->create(['school_id' => $school->id, 'attempt_id' => $attempt->id, 'question_id' => $question->id]);
        });

        TenantModelRegistry::register(LibraryItem::class, fn (School $school): LibraryItem => LibraryItem::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(LibraryCopy::class, function (School $school): LibraryCopy {
            $item = LibraryItem::factory()->create(['school_id' => $school->id]);

            return LibraryCopy::factory()->create(['school_id' => $school->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(BorrowerCategory::class, fn (School $school): BorrowerCategory => BorrowerCategory::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Loan::class, function (School $school): Loan {
            $item = LibraryItem::factory()->create(['school_id' => $school->id]);
            $copy = LibraryCopy::factory()->create(['school_id' => $school->id, 'item_id' => $item->id]);
            [$student, $term] = $this->studentAndTerm($school);

            return Loan::factory()->create([
                'school_id' => $school->id, 'term_id' => $term->id, 'copy_id' => $copy->id, 'borrower_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(BulkTextbookIssue::class, function (School $school): BulkTextbookIssue {
            [, $term] = $this->studentAndTerm($school);
            $class = SchoolClass::factory()->for($school)->create();

            return BulkTextbookIssue::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'class_id' => $class->id]);
        });

        TenantModelRegistry::register(AcquisitionRequest::class, fn (School $school): AcquisitionRequest => AcquisitionRequest::factory()->create([
            'school_id' => $school->id, 'requested_by' => User::factory(),
        ]));

        TenantModelRegistry::register(LibraryStockTake::class, fn (School $school): LibraryStockTake => LibraryStockTake::factory()->create(['school_id' => $school->id]));
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

    /**
     * Book K ACA-08. Every FK is derived explicitly from the same
     * `$school`/`$term`/`$year` triple — a course space's `TeachingGroup`
     * must never end up pointing at a different school than the course
     * space itself (see `CourseSpaceFactory`'s own docblock for why its
     * bare default isn't reused here).
     */
    private function courseSpaceFor(School $school): CourseSpace
    {
        [, $term, $year] = $this->studentAndTerm($school);
        $framework = CurriculumFramework::factory()->for($school)->create();
        $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $group = TeachingGroup::factory()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
            'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id,
        ]);

        return CourseSpace::factory()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
            'subject_id' => $subject->id, 'teaching_group_id' => $group->id,
        ]);
    }

    /**
     * Book K ACA-09. Every FK derived explicitly from the same
     * `$school`/`$term` pair — see `courseSpaceFor()`'s own docblock
     * for why a bare nested-factory default isn't reused here.
     */
    private function cbtTestFor(School $school): CbtTest
    {
        [, $term] = $this->studentAndTerm($school);
        $subject = Subject::factory()->for($school)->create(['framework_id' => CurriculumFramework::factory()->for($school)->create()->id]);

        return CbtTest::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'subject_id' => $subject->id]);
    }
}
