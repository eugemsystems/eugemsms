<?php

declare(strict_types=1);

namespace Modules\People\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Academic\Models\Subject;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Domain\DataObjects\Files\FileCategoryDefinition;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\FileCategoryRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\PermissionRegistry;
use Modules\Core\Domain\Registry\ScheduledTaskHandlerRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Console\Tasks\ExpireLapsedOffersTask;
use Modules\People\Domain\Actions\CheckStaffDocumentExpiryAction;
use Modules\People\Domain\Actions\CheckStudentDocumentExpiryAction;
use Modules\People\Domain\Events\LearnerEnrolled;
use Modules\People\Domain\Events\LearnerStatusChanged;
use Modules\People\Domain\Listeners\CreateAlumniRecordOnGraduationListener;
use Modules\People\Domain\Listeners\RecordLearnerTimelineListener;
use Modules\People\Models\AlumniCareerUpdate;
use Modules\People\Models\AlumniEvent;
use Modules\People\Models\AlumniHouseGroup;
use Modules\People\Models\Alumnus;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationDocument;
use Modules\People\Models\BursaryEndowment;
use Modules\People\Models\CapitalCampaign;
use Modules\People\Models\Department;
use Modules\People\Models\Donation;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\DutyRoster;
use Modules\People\Models\Enquiry;
use Modules\People\Models\EnquiryActivity;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\EntranceExamCandidate;
use Modules\People\Models\EstablishmentPost;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianContactUpdate;
use Modules\People\Models\GuardianVerification;
use Modules\People\Models\Household;
use Modules\People\Models\HouseholdMember;
use Modules\People\Models\Intake;
use Modules\People\Models\Interview;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Pledge;
use Modules\People\Models\Sponsorship;
use Modules\People\Models\SponsorshipBeneficiary;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffAppraisal;
use Modules\People\Models\StaffContract;
use Modules\People\Models\StaffDisciplinaryCase;
use Modules\People\Models\StaffDocument;
use Modules\People\Models\StaffExitChecklist;
use Modules\People\Models\StaffQualification;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;
use Modules\People\Models\StudentDocument;
use Modules\People\Models\StudentEnrolment;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentPriorResult;
use Modules\People\Models\StudentPriorSchool;
use Modules\People\Models\StudentSibling;
use Modules\People\Models\StudentTimelineEvent;
use Modules\People\Models\TeacherAllocation;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PeopleServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'People';

    protected string $nameLower = 'people';

    public function register(): void
    {
        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerScheduledTasks();
        $this->registerEventListeners();
        $this->registerPermissions();
        $this->registerLivewireRoutes();
        $this->registerFileCategories();
        $this->registerNotificationKeys();
    }

    /**
     * Book C PPL-03: the invitation a guardian gets when they are given parent-app access.
     */
    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'people.parent_app_invitation',
            variables: ['guardian.name', 'school.name', 'guardian.phone'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'guardian',
            isTransactional: true,
        ));
    }

    /**
     * Book C PPL-04 §2/BR-PPL-04-018. Mirrors `IntelligenceServiceProvider::registerFileCategories()`'s
     * own registration shape — `AddStaffDocumentAction` needs a real
     * `files.id` to attach, and no category existed for one yet.
     */
    private function registerFileCategories(): void
    {
        foreach (['student_document' => 'Student Document', 'guardian_verification' => 'Guardian Verification', 'application_document' => 'Application Document', 'staff_qualification' => 'Staff Qualification'] as $key => $label) {
            FileCategoryRegistry::register(new FileCategoryDefinition(
                $key, $label, 'PEOPLE',
                ['application/pdf', 'image/jpeg', 'image/png'], 10 * 1024 * 1024, isSensitive: true,
            ));
        }
        FileCategoryRegistry::register(new FileCategoryDefinition(
            'staff_document', 'Staff Document', 'PEOPLE',
            ['application/pdf', 'image/jpeg', 'image/png'], 10 * 1024 * 1024, isSensitive: true,
        ));
    }

    /**
     * Book C PPL-01 §10 — only the subset of the spec's own permission
     * list that this pass's admin UI actually exercises (see
     * `docs/specification/PROGRESS.md` and `.ai/rules/people.md` for
     * what PPL-01's screens cover and what's deferred). Registered via
     * the same `PermissionRegistry::register($moduleCode, [...])`
     * mechanism `FinanceServiceProvider` uses.
     */
    private function registerPermissions(): void
    {
        PermissionRegistry::register('ALUMNI', [
            'view' => ['description' => 'View the alumni directory and profiles, including frozen academic summaries and giving history.'],
            'career.verify' => ['description' => 'Record career updates for an alumnus and confirm them.'],
            'contact.manage' => ['description' => 'Record an alumnus’s contact opt-out and offer them a portal account.'],
            'event.manage' => ['description' => 'Create alumni events on the school calendar.'],
            'campaign.manage' => ['description' => 'Create and review capital campaigns.'],
            'pledge.manage' => ['description' => 'Record donor pledges.'],
            'donation.record' => ['description' => 'Record a donation received — posts to the general ledger.', 'dangerous' => true],
            'endowment.manage' => ['description' => 'Create bursary endowments that fund a discount scheme.', 'dangerous' => true],
        ]);

        PermissionRegistry::register('PEOPLE', [
            'students.document_manage' => ['description' => 'Attach, verify and review a learner\'s documents, prior schooling and sibling links.'],
            'students.transfer' => ['description' => 'Transfer a learner out of the school after the clearance check; the head may override a failed clearance with a reason.', 'dangerous' => true],
            'students.id_card_issue' => ['description' => 'Produce learner ID cards.'],
            'guardians.household_manage' => ['description' => 'Create households and move learners and guardians between them.'],
            'guardians.merge' => ['description' => 'Merge a duplicate guardian record into another.', 'dangerous' => true],
            'guardians.portal_access' => ['description' => 'Give or withdraw a guardian\'s access to the parent app.', 'dangerous' => true],
            'guardians.verify' => ['description' => 'Record and verify a guardian\'s ID document and collection photo.', 'dangerous' => true],
            'guardians.update' => ['description' => 'Approve or reject a guardian\'s requested change of phone, email or address.'],
            'sponsorships.manage' => ['description' => 'Create sponsorships, add beneficiaries and end support.', 'dangerous' => true],
            'admissions.enquiry_manage' => ['description' => 'Work the enquiry pipeline: log contact, move stages, mark lost.'],
            'admissions.exam_manage' => ['description' => 'Schedule entrance exams, seat candidates, capture marks and publish results.'],
            'admissions.interview_manage' => ['description' => 'Schedule applicant interviews and record panel outcomes.'],
            'admissions.report_view' => ['description' => 'See the admissions funnel.'],
            'staff.qualification_manage' => ['description' => 'Record and verify staff qualifications.'],
            'students.view' => ['description' => 'View the learner directory and profiles.'],
            'students.create' => ['description' => 'Enrol a new learner.'],
            'students.update' => ['description' => 'Edit a learner\'s non-billing profile fields.'],
            'students.change_status' => ['description' => 'Change a learner\'s status (suspend, withdraw, readmit, graduate).'],
            'students.change_billing_attribute' => ['description' => 'Change a learner\'s enrolment type, residency, grade level, class, section, or pathway.', 'dangerous' => true],
            'students.merge' => ['description' => 'Scan for possible duplicate learner records.', 'dangerous' => true],
            'guardians.view' => ['description' => 'View the guardian directory and profiles.'],
            'guardians.create' => ['description' => 'Register a new guardian.'],
            'guardians.manage_relationships' => ['description' => 'Link or unlink a guardian from a learner and set their rights.', 'dangerous' => true],
            'admissions.intake_manage' => ['description' => 'Set up and manage admissions intakes.'],
            'admissions.application_view' => ['description' => 'View the application pipeline and individual applications.'],
            'admissions.application_create' => ['description' => 'Capture a new application.'],
            'admissions.application_review' => ['description' => 'Progress an application through fee payment, decline, acceptance, and deposit.'],
            'admissions.offer_make' => ['description' => 'Make or expire a place offer.', 'dangerous' => true],
            'admissions.convert' => ['description' => 'Convert an accepted application into a learner record.', 'dangerous' => true],
            'staff.view' => ['description' => 'View the staff directory and profiles.'],
            'staff.view_compensation' => ['description' => 'View a staff member\'s salary, banking, and statutory identifiers.', 'dangerous' => true],
            'staff.create' => ['description' => 'Create a new staff record.'],
            'staff.contract_manage' => ['description' => 'Create, renew, or terminate a staff contract.'],
            'staff.establishment_manage' => ['description' => 'Manage departments and establishment posts.'],
            'staff.allocate' => ['description' => 'Allocate or end a teacher\'s subject/class allocation.'],
            'staff.leave_view' => ['description' => 'View and submit leave requests and balances.'],
            'staff.leave_approve' => ['description' => 'Approve, reject, or manage leave types and balances.'],
            'staff.duty_manage' => ['description' => 'Create duty rosters, generate assignments, and approve swaps.'],
            'staff.appraisal_manage' => ['description' => 'Manage staff appraisals through their full cycle.'],
            'staff.appraisal_rubric_manage' => ['description' => 'Create and manage structured staff appraisal rubrics.'],
            'staff.disciplinary_manage' => ['description' => 'Report, view, and advance staff disciplinary cases.', 'dangerous' => true],
            'staff.document_manage' => ['description' => 'Add staff documents and review the compliance expiry dashboard.'],
            'staff.exit_process' => ['description' => 'Initiate and process a staff member\'s exit.', 'dangerous' => true],
        ]);
    }

    /**
     * Mirrors `FinanceServiceProvider::registerLivewireRoutes()`'s own
     * `Livewire::addLocation()` call and reasoning.
     */
    private function registerLivewireRoutes(): void
    {
        Livewire::addLocation(classNamespace: 'Modules\People\Livewire');

        Route::middleware('web')->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/students.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/guardians.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/admissions.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/staff.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/alumni.php');
        });
    }

    /**
     * Book K PPL-06 §3 ⭐/BR-PPL-06-001.
     */
    private function registerEventListeners(): void
    {
        Event::listen(LearnerStatusChanged::class, CreateAlumniRecordOnGraduationListener::class);
        Event::listen(LearnerStatusChanged::class, [RecordLearnerTimelineListener::class, 'onStatusChanged']);
        Event::listen(LearnerEnrolled::class, [RecordLearnerTimelineListener::class, 'onEnrolled']);
    }

    /**
     * Book C PPL-01 §11.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['students.admission_number_pattern', 'string', '{SCHOOL}/{YEAR}/{SEQ:4}', 'Admission number format.'],
            ['students.document_expiry_warning_days', 'json', '[90,30,7]', 'Days before a learner document expires on which an alert is raised.'],
            ['students.min_age_years_ecd_a', 'int', '3', 'Minimum age for ECD A.'],
            ['students.max_age_variance_years', 'int', '3', 'Years a learner may vary from a grade level\'s typical age before a warning fires.'],
            ['identity.national_registration_required', 'bool', '0', 'Whether a national registration number is mandatory on enrolment.'],
            ['identity.national_registration_pattern', 'string', '', 'Regex validating a national registration number, school-scoped.'],
            ['students.allow_backdated_attribute_change', 'bool', '1', 'Whether a billing attribute change may be backdated within the current term.'],
            ['students.backdate_limit_days', 'int', '60', 'Furthest a billing attribute change may be backdated.'],
            ['students.duplicate_check_on_create', 'bool', '1', 'Whether duplicate detection runs when a learner is created.'],
            ['students.portal_min_grade_ordinal', 'int', '4', 'Lowest grade-level ordinal permitted a student portal account.'],
            ['guardians.max_per_learner', 'int', '6', 'Maximum number of guardian relationships a learner may carry.'],
            ['guardians.duplicate_check_on_create', 'bool', '1', 'Whether duplicate detection runs when a guardian is created.'],
            ['admissions.override_capacity', 'bool', '0', 'Whether conversion may proceed past an intake\'s target places, with a reason (BR-PPL-02-008).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'PPL',
                'group_key' => 'students',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => $dataType === 'bool' ? 'toggle' : 'text',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }

        $staffDefinitions = [
            ['staff.staff_number_pattern', 'string', '{SCHOOL}/STF/{SEQ:4}', 'Staff number format.'],
            ['staff.default_max_weekly_periods', 'int', '30', 'Default teaching-period ceiling when a staff member has none of their own.'],
            ['staff.enforce_workload_ceiling', 'bool', '0', 'Whether exceeding the workload ceiling blocks a teacher allocation (BR-PPL-04-006).'],
            ['staff.contract_expiry_warning_days', 'int', '60', 'Days before contract expiry that alerting fires (BR-PPL-04-003).'],
            ['staff.default_notice_period_days', 'int', '30', 'Default contract notice period.'],
            ['staff.probation_months', 'int', '3', 'Default probation length.'],
            ['staff.sick_note_required_after_days', 'int', '2', 'Days of sick leave before a note is required.'],
            ['staff.sick_note_grace_days', 'int', '3', 'Grace window for supplying a required leave document (BR-PPL-04-012).'],
            ['staff.duty_fairness_balancing', 'bool', '1', 'Whether duty roster generation levels duty counts across eligible staff.'],
            ['staff.auto_deactivate_account_on_exit', 'bool', '1', 'Whether a staff member\'s user account deactivates automatically on exit.'],
            ['staff.document_expiry_warning_days', 'json', '[90,30,7]', 'Days before a staff document expires that a compliance alert fires (BR-PPL-04-018).'],
        ];

        foreach ($staffDefinitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'PPL',
                'group_key' => 'staff',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => match ($dataType) {
                    'bool' => 'toggle',
                    'json' => 'tags',
                    default => 'text',
                },
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
        TenantModelRegistry::register(GuardianContactUpdate::class, fn (School $school): GuardianContactUpdate => GuardianContactUpdate::factory()->for($school)->create());
        TenantModelRegistry::register(StaffQualification::class, fn (School $school): StaffQualification => StaffQualification::factory()->for($school)->create());

        TenantModelRegistry::register(StudentDocument::class, fn (School $school): StudentDocument => StudentDocument::factory()->for($school)->create());

        TenantModelRegistry::register(StudentPriorSchool::class, fn (School $school): StudentPriorSchool => StudentPriorSchool::factory()->for($school)->create());

        TenantModelRegistry::register(StudentSibling::class, fn (School $school): StudentSibling => StudentSibling::factory()->for($school)->create());

        TenantModelRegistry::register(StudentTimelineEvent::class, fn (School $school): StudentTimelineEvent => StudentTimelineEvent::factory()->for($school)->create());

        TenantModelRegistry::register(Household::class, fn (School $school): Household => Household::factory()->for($school)->create());

        TenantModelRegistry::register(HouseholdMember::class, fn (School $school): HouseholdMember => HouseholdMember::factory()->for($school)->create());

        TenantModelRegistry::register(Sponsorship::class, fn (School $school): Sponsorship => Sponsorship::factory()->for($school)->create());

        TenantModelRegistry::register(SponsorshipBeneficiary::class, fn (School $school): SponsorshipBeneficiary => SponsorshipBeneficiary::factory()->for($school)->create());

        TenantModelRegistry::register(GuardianVerification::class, fn (School $school): GuardianVerification => GuardianVerification::factory()->for($school)->create());

        TenantModelRegistry::register(Enquiry::class, fn (School $school): Enquiry => Enquiry::factory()->for($school)->create());

        TenantModelRegistry::register(EnquiryActivity::class, fn (School $school): EnquiryActivity => EnquiryActivity::factory()->for($school)->create());

        TenantModelRegistry::register(ApplicationDocument::class, fn (School $school): ApplicationDocument => ApplicationDocument::factory()->for($school)->create());

        TenantModelRegistry::register(EntranceExam::class, fn (School $school): EntranceExam => EntranceExam::factory()->for($school)->create());

        TenantModelRegistry::register(EntranceExamCandidate::class, fn (School $school): EntranceExamCandidate => EntranceExamCandidate::factory()->for($school)->create());

        TenantModelRegistry::register(Interview::class, fn (School $school): Interview => Interview::factory()->for($school)->create());
        TenantModelRegistry::register(Student::class, function (School $school): Student {
            $section = SchoolSection::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();

            return Student::factory()->for($school)->create([
                'section_id' => $section->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
        });

        TenantModelRegistry::register(StudentEnrolment::class, function (School $school): StudentEnrolment {
            $section = SchoolSection::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
            $student = Student::factory()->for($school)->create([
                'section_id' => $section->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return StudentEnrolment::factory()->create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'section_id' => $section->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
        });

        TenantModelRegistry::register(StudentAttributeChange::class, function (School $school): StudentAttributeChange {
            $section = SchoolSection::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
            $student = Student::factory()->for($school)->create([
                'section_id' => $section->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return StudentAttributeChange::factory()->create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(Guardian::class, fn (School $school): Guardian => Guardian::factory()->for($school)->create());

        TenantModelRegistry::register(StudentGuardian::class, function (School $school): StudentGuardian {
            $section = SchoolSection::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
            $student = Student::factory()->for($school)->create([
                'section_id' => $section->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
            $guardian = Guardian::factory()->for($school)->create();

            return StudentGuardian::factory()->create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
            ]);
        });

        TenantModelRegistry::register(FeeLiability::class, function (School $school): FeeLiability {
            $section = SchoolSection::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
            $student = Student::factory()->for($school)->create([
                'section_id' => $section->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
            $guardian = Guardian::factory()->for($school)->create();

            return FeeLiability::factory()->create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
            ]);
        });

        TenantModelRegistry::register(Intake::class, function (School $school): Intake {
            $year = AcademicYear::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();

            return Intake::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'grade_level_id' => $gradeLevel->id,
            ]);
        });

        TenantModelRegistry::register(Application::class, function (School $school): Application {
            $year = AcademicYear::factory()->for($school)->create();
            $intakeGradeLevel = GradeLevel::factory()->for($school)->create();
            $intake = Intake::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'grade_level_id' => $intakeGradeLevel->id,
            ]);
            $requestedGradeLevel = GradeLevel::factory()->for($school)->create();

            return Application::factory()->create([
                'school_id' => $school->id,
                'intake_id' => $intake->id,
                'requested_grade_level_id' => $requestedGradeLevel->id,
            ]);
        });

        // ApplicationGuardian has no `school_id`/`BelongsToSchool` — it is
        // reached only through its owning Application, so it carries no
        // tenancy boundary of its own to test.

        TenantModelRegistry::register(Department::class, fn (School $school): Department => Department::factory()->for($school)->create());

        TenantModelRegistry::register(EstablishmentPost::class, fn (School $school): EstablishmentPost => EstablishmentPost::factory()->for($school)->create());

        TenantModelRegistry::register(Staff::class, fn (School $school): Staff => Staff::factory()->for($school)->create());

        TenantModelRegistry::register(StaffContract::class, function (School $school): StaffContract {
            $staff = Staff::factory()->for($school)->create();

            return StaffContract::factory()->for($school)->create(['staff_id' => $staff->id]);
        });

        TenantModelRegistry::register(TeacherAllocation::class, function (School $school): TeacherAllocation {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $staff = Staff::factory()->for($school)->teaching()->create();
            $subject = Subject::factory()->for($school)->create();
            $class = SchoolClass::factory()->for($school)->for($year)->create();
            $user = User::factory()->create();

            return TeacherAllocation::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'staff_id' => $staff->id,
                'subject_id' => $subject->id,
                'class_id' => $class->id,
                'allocated_by' => $user->id,
            ]);
        });

        TenantModelRegistry::register(StaffWorkload::class, function (School $school): StaffWorkload {
            $term = Term::factory()->for($school)->create();
            $staff = Staff::factory()->for($school)->create();

            return StaffWorkload::factory()->create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(LeaveType::class, fn (School $school): LeaveType => LeaveType::factory()->for($school)->create());

        TenantModelRegistry::register(LeaveBalance::class, function (School $school): LeaveBalance {
            $staff = Staff::factory()->for($school)->create();
            $leaveType = LeaveType::factory()->for($school)->create();
            $year = AcademicYear::factory()->for($school)->create();

            return LeaveBalance::factory()->create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'leave_type_id' => $leaveType->id,
                'academic_year_id' => $year->id,
            ]);
        });

        TenantModelRegistry::register(LeaveRequest::class, function (School $school): LeaveRequest {
            $staff = Staff::factory()->for($school)->create();
            $leaveType = LeaveType::factory()->for($school)->create();
            $year = AcademicYear::factory()->for($school)->create();

            return LeaveRequest::factory()->create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'leave_type_id' => $leaveType->id,
                'academic_year_id' => $year->id,
            ]);
        });

        TenantModelRegistry::register(StaffDocument::class, function (School $school): StaffDocument {
            $staff = Staff::factory()->for($school)->create();
            $file = File::factory()->create(['school_id' => $school->id]);

            return StaffDocument::factory()->create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'file_id' => $file->id,
            ]);
        });

        TenantModelRegistry::register(DutyRoster::class, function (School $school): DutyRoster {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return DutyRoster::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(DutyAssignment::class, function (School $school): DutyAssignment {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $roster = DutyRoster::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
            ]);
            $staff = Staff::factory()->for($school)->create();

            return DutyAssignment::factory()->create([
                'school_id' => $school->id,
                'roster_id' => $roster->id,
                'staff_id' => $staff->id,
            ]);
        });

        TenantModelRegistry::register(StaffAppraisal::class, function (School $school): StaffAppraisal {
            $staff = Staff::factory()->for($school)->create();
            $appraiser = Staff::factory()->for($school)->create();
            $year = AcademicYear::factory()->for($school)->create();

            return StaffAppraisal::factory()->create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'appraiser_staff_id' => $appraiser->id,
                'academic_year_id' => $year->id,
            ]);
        });

        TenantModelRegistry::register(StaffDisciplinaryCase::class, function (School $school): StaffDisciplinaryCase {
            $staff = Staff::factory()->for($school)->create();
            $user = User::factory()->create();

            return StaffDisciplinaryCase::factory()->create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'reported_by' => $user->id,
            ]);
        });

        TenantModelRegistry::register(StaffExitChecklist::class, function (School $school): StaffExitChecklist {
            $staff = Staff::factory()->for($school)->create();
            $user = User::factory()->create();

            return StaffExitChecklist::factory()->create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'initiated_by' => $user->id,
            ]);
        });

        TenantModelRegistry::register(StudentPriorResult::class, fn (School $school): StudentPriorResult => StudentPriorResult::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Alumnus::class, fn (School $school): Alumnus => $this->alumnusFor($school));

        TenantModelRegistry::register(AlumniHouseGroup::class, fn (School $school): AlumniHouseGroup => AlumniHouseGroup::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AlumniCareerUpdate::class, function (School $school): AlumniCareerUpdate {
            $alumnus = $this->alumnusFor($school);

            return AlumniCareerUpdate::factory()->create(['school_id' => $school->id, 'alumnus_id' => $alumnus->id]);
        });

        TenantModelRegistry::register(CapitalCampaign::class, function (School $school): CapitalCampaign {
            $account = Account::factory()->for($school)->income()->create();

            return CapitalCampaign::factory()->create(['school_id' => $school->id, 'income_account_id' => $account->id]);
        });

        TenantModelRegistry::register(BursaryEndowment::class, function (School $school): BursaryEndowment {
            $scheme = DiscountScheme::factory()->for($school)->create();

            return BursaryEndowment::factory()->create(['school_id' => $school->id, 'funds_scheme_id' => $scheme->id]);
        });

        TenantModelRegistry::register(Pledge::class, fn (School $school): Pledge => Pledge::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Donation::class, function (School $school): Donation {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return Donation::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);
        });

        TenantModelRegistry::register(AlumniEvent::class, function (School $school): AlumniEvent {
            $year = AcademicYear::factory()->for($school)->create();
            $calendarEvent = CalendarEvent::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);

            return AlumniEvent::factory()->create(['school_id' => $school->id, 'calendar_event_id' => $calendarEvent->id]);
        });
    }

    /**
     * Book K PPL-06. Every FK derived explicitly from the same
     * `$school` — a student's `grade_level_id` must match the
     * alumnus's own `final_grade_level_id` override.
     */
    private function alumnusFor(School $school): Alumnus
    {
        $section = SchoolSection::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
        $student = Student::factory()->for($school)->create([
            'section_id' => $section->id, 'grade_level_id' => $gradeLevel->id,
        ]);

        return Alumnus::factory()->create([
            'school_id' => $school->id, 'student_id' => $student->id, 'final_grade_level_id' => $gradeLevel->id,
        ]);
    }

    /**
     * Per-school periodic jobs, run by `serp:run-task`. Each handler only
     * calls the module's existing Action for one school.
     */
    private function registerScheduledTasks(): void
    {
        ScheduledTaskHandlerRegistry::register(
            key: 'people.check_student_document_expiry',
            moduleCode: 'PPL-01',
            name: 'Check Learner Document Expiry',
            cron: '50 6 * * *',
            handler: static function (School $school): string {
                $r = app(CheckStudentDocumentExpiryAction::class)->execute($school->id);

                return count($r).' document(s) expiring';
            },
            description: 'Alerts on learner permits and documents nearing expiry.',
            alertIfNotRunWithinMinutes: 1560,
        );

        ScheduledTaskHandlerRegistry::register(
            key: 'people.expire_lapsed_offers',
            moduleCode: 'PPL-02',
            name: 'Expire Lapsed Offers',
            cron: '10 0 * * *',
            handler: ExpireLapsedOffersTask::class,
            description: 'Lapses offers past their expiry and returns the place to the waitlist.',
            alertIfNotRunWithinMinutes: 1560,
        );

        ScheduledTaskHandlerRegistry::register(
            key: 'people.check_staff_document_expiry',
            moduleCode: 'PPL-04',
            name: 'Check Staff Document Expiry',
            cron: '20 6 * * *',
            handler: static function (School $school): string {
                $r = app(CheckStaffDocumentExpiryAction::class)->execute($school->id);

                return count($r).' alert(s)';
            },
            description: 'Alerts on staff documents and certificates nearing expiry.',
            alertIfNotRunWithinMinutes: 1560,
        );
    }
}
