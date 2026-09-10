<?php

declare(strict_types=1);

namespace Modules\People\Providers;

use App\Models\User;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Application;
use Modules\People\Models\Department;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\DutyRoster;
use Modules\People\Models\EstablishmentPost;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\Intake;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffAppraisal;
use Modules\People\Models\StaffContract;
use Modules\People\Models\StaffDisciplinaryCase;
use Modules\People\Models\StaffDocument;
use Modules\People\Models\StaffExitChecklist;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;
use Modules\People\Models\StudentEnrolment;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentPriorResult;
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
    }

    /**
     * Book C PPL-01 §11.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['students.admission_number_pattern', 'string', '{SCHOOL}/{YEAR}/{SEQ:4}', 'Admission number format.'],
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
    }
}
