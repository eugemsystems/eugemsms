<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Boarding\Domain\Actions\AllocateBedAction;
use Modules\Boarding\Domain\Actions\CreateRollCallPointAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\DataObjects\AllocateBedData;
use Modules\Boarding\Domain\DataObjects\CreateRollCallPointData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\Actions\AdministerMedicationAction;
use Modules\Welfare\Domain\Actions\AdmitToSickBayAction;
use Modules\Welfare\Domain\Actions\DeclareMedicalConditionAction;
use Modules\Welfare\Domain\Actions\GrantMedicalConsentAction;
use Modules\Welfare\Domain\Actions\MakeExternalReferralAction;
use Modules\Welfare\Domain\Actions\ResolveMedicalTierAction;
use Modules\Welfare\Domain\DataObjects\AdministerMedicationData;
use Modules\Welfare\Domain\DataObjects\AdmitToSickBayData;
use Modules\Welfare\Domain\DataObjects\DeclareMedicalConditionData;
use Modules\Welfare\Domain\DataObjects\GrantMedicalConsentData;
use Modules\Welfare\Domain\DataObjects\MakeExternalReferralData;
use Modules\Welfare\Domain\Support\MedicalTier;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\ControlledStockLogEntry;
use Modules\Welfare\Models\MedicationAdministration;

/**
 * @return array{school: School, year: AcademicYear, term: Term, gradeLevel: GradeLevel, user: User}
 */
function brd06Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    Permission::factory()->create(['name' => 'health.clinical.view']);
    Permission::factory()->create(['name' => 'health.actionable.view']);

    return compact('school', 'year', 'term', 'gradeLevel', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function brd06GivePermission(array $f, User $user, string $permissionName): void
{
    $role = Role::factory()->forSchool($f['school']->id)->create();
    $role->givePermissionTo(Permission::where('name', $permissionName)->firstOrFail());

    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $f['school']->id));
    SchoolContext::set($f['school']);
}

/**
 * @param  array<string, mixed>  $f
 */
function brd06Student(array $f): Student
{
    return Student::factory()->create(['school_id' => $f['school']->id, 'grade_level_id' => $f['gradeLevel']->id]);
}

it('resolves the clinical tier for a permitted nurse and logs the read, but only existence for an uninvolved teacher', function (): void {
    $f = brd06Fixture();
    $student = brd06Student($f);

    $nurse = User::factory()->create();
    brd06GivePermission($f, $nurse, 'health.clinical.view');

    $tier = app(ResolveMedicalTierAction::class)->execute($nurse, $student);
    expect($tier)->toBe(MedicalTier::Clinical);

    $log = DB::table('data_access_log')->where('resource_type', 'clinical_record')->where('user_id', $nurse->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->resource_id)->toBe($student->id);

    $teacher = User::factory()->create();
    brd06GivePermission($f, $teacher, 'health.actionable.view');

    $existenceTier = app(ResolveMedicalTierAction::class)->execute($teacher, $student);
    expect($existenceTier)->toBe(MedicalTier::Existence);

    $stranger = User::factory()->create();
    expect(fn () => app(ResolveMedicalTierAction::class)->execute($stranger, $student))
        ->toThrow(InsufficientScopeException::class);
});

it('declares a dietary-affecting condition, flags the student, and feeds BRD-04 with the public summary only', function (): void {
    $f = brd06Fixture();
    $student = brd06Student($f);

    $condition = app(DeclareMedicalConditionAction::class)->execute(new DeclareMedicalConditionData(
        schoolId: $f['school']->id, studentId: $student->id, conditionType: 'allergy',
        name: 'Severe peanut allergy — anaphylaxis risk', severity: 'severe', declaredBy: 'guardian',
        effectiveFrom: now(), category: 'anaphylaxis', publicSummary: 'Severe nut allergy — EpiPen',
        affectsDietary: true, requiresEmergencyPlan: true,
    ));

    expect($condition->verified_by_nurse)->toBeFalse();

    $student->refresh();
    expect($student->has_medical_alert)->toBeTrue()
        ->and($student->has_allergy_alert)->toBeTrue()
        ->and($student->has_dietary_requirement)->toBeTrue();

    $requirement = DietaryRequirement::where('student_id', $student->id)->first();
    expect($requirement)->not->toBeNull()
        ->and($requirement->description)->toBe('Severe nut allergy — EpiPen')
        ->and($requirement->requires_epipen)->toBeTrue()
        ->and($requirement->medical_source_id)->toBe($condition->id);
});

it('refuses to administer medication without a valid consent, naming the missing consent, and succeeds once granted', function (): void {
    $f = brd06Fixture();
    $student = brd06Student($f);
    $nurse = User::factory()->create();

    expect(fn () => app(AdministerMedicationAction::class)->execute(new AdministerMedicationData(
        schoolId: $f['school']->id, studentId: $student->id, medicationName: 'Paracetamol', dose: '500mg',
        route: 'oral', administeredAt: now(), administeredByUserId: $nurse->id,
    )))->toThrow(ValidationException::class);

    $guardian = Guardian::factory()->for($f['school'])->create();
    StudentGuardian::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id,
        'may_authorise_medical' => true,
    ]);

    app(GrantMedicalConsentAction::class)->execute(new GrantMedicalConsentData(
        schoolId: $f['school']->id, studentId: $student->id, guardianId: $guardian->id,
        consentType: 'otc_medication', granted: true, grantedAt: now(), grantedVia: 'form', effectiveFrom: now(),
    ));

    $administration = app(AdministerMedicationAction::class)->execute(new AdministerMedicationData(
        schoolId: $f['school']->id, studentId: $student->id, medicationName: 'Paracetamol', dose: '500mg',
        route: 'oral', administeredAt: now(), administeredByUserId: $nurse->id,
    ));

    expect($administration->consent_reference)->toStartWith('consent:')
        ->and($administration->outcome)->toBe('given');
});

it('requires a second, different witness for controlled medication and refuses expired stock', function (): void {
    $f = brd06Fixture();
    $student = brd06Student($f);
    $nurse = User::factory()->create();
    $guardian = Guardian::factory()->for($f['school'])->create();
    StudentGuardian::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id,
        'may_authorise_medical' => true,
    ]);
    app(GrantMedicalConsentAction::class)->execute(new GrantMedicalConsentData(
        schoolId: $f['school']->id, studentId: $student->id, guardianId: $guardian->id,
        consentType: 'otc_medication', granted: true, grantedAt: now(), grantedVia: 'form', effectiveFrom: now(),
    ));

    $controlledStock = ClinicStock::factory()->create(['school_id' => $f['school']->id, 'is_controlled' => true, 'quantity_on_hand' => 10]);

    expect(fn () => app(AdministerMedicationAction::class)->execute(new AdministerMedicationData(
        schoolId: $f['school']->id, studentId: $student->id, medicationName: 'Morphine', dose: '5mg',
        route: 'oral', administeredAt: now(), administeredByUserId: $nurse->id, clinicStockId: $controlledStock->id,
    )))->toThrow(InvalidStateTransitionException::class);

    $witness = User::factory()->create();
    $administration = app(AdministerMedicationAction::class)->execute(new AdministerMedicationData(
        schoolId: $f['school']->id, studentId: $student->id, medicationName: 'Morphine', dose: '5mg',
        route: 'oral', administeredAt: now(), administeredByUserId: $nurse->id, clinicStockId: $controlledStock->id,
        witnessedByUserId: $witness->id,
    ));

    expect($administration->witnessed_by)->toBe($witness->id);
    expect((float) $controlledStock->fresh()->quantity_on_hand)->toBe(9.0);

    $log = ControlledStockLogEntry::where('clinic_stock_id', $controlledStock->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->witnessed_by)->toBe($witness->id)
        ->and((float) $log->balance_after)->toBe(9.0);

    $expiredStock = ClinicStock::factory()->create(['school_id' => $f['school']->id, 'expiry_date' => now()->subDay()->toDateString()]);

    expect(fn () => app(AdministerMedicationAction::class)->execute(new AdministerMedicationData(
        schoolId: $f['school']->id, studentId: $student->id, medicationName: 'Ibuprofen', dose: '200mg',
        route: 'oral', administeredAt: now(), administeredByUserId: $nurse->id, clinicStockId: $expiredStock->id,
    )))->toThrow(ValidationException::class);
});

it('records emergency-provision medication without consent loudly, not silently, and notifies the guardian', function (): void {
    $f = brd06Fixture();
    $student = brd06Student($f);
    $nurse = User::factory()->create();
    $decisionMaker = User::factory()->create();

    $administration = app(AdministerMedicationAction::class)->execute(new AdministerMedicationData(
        schoolId: $f['school']->id, studentId: $student->id, medicationName: 'Adrenaline', dose: '0.3mg',
        route: 'injection', administeredAt: now(), administeredByUserId: $nurse->id,
        emergencyProvision: true, emergencyDecisionMakerUserId: $decisionMaker->id,
    ));

    expect($administration->consent_reference)->toBe("emergency_provision:{$decisionMaker->id}");
});

it('enforces medication_administrations as append-only', function (): void {
    $f = brd06Fixture();
    $record = MedicationAdministration::factory()->create(['school_id' => $f['school']->id]);

    expect(fn () => $record->update(['dose' => '999mg']))
        ->toThrow(InvalidStateTransitionException::class);

    expect(fn () => $record->delete())
        ->toThrow(InvalidStateTransitionException::class);
});

it('pre-populates sick_bay and hospital roll status for the next roll call', function (): void {
    $f = brd06Fixture();
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => 'male']);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);
    $nurse = User::factory()->create();

    $sickStudent = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'male', 'grade_level_id' => $f['gradeLevel']->id]);
    $hospitalStudent = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'male', 'grade_level_id' => $f['gradeLevel']->id]);

    foreach ([$sickStudent, $hospitalStudent] as $student) {
        $bed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);
        BedAllocation::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id,
            'status' => 'confirmed', 'allocated_by' => $f['user']->id,
        ]);
    }

    app(AdmitToSickBayAction::class)->execute(new AdmitToSickBayData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $sickStudent->id, admittedAt: now(),
        admittedByUserId: $nurse->id, presentingComplaint: 'Fever', severity: 'minor',
    ));

    app(MakeExternalReferralAction::class)->execute(new MakeExternalReferralData(
        schoolId: $f['school']->id, studentId: $hospitalStudent->id, referralType: 'hospital',
        facilityName: 'Parirenyatwa', reason: 'Suspected fracture', urgency: 'urgent',
        referredAt: now(), referredByUserId: $nurse->id,
    ));

    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'SUPPER', name: 'Supper', scheduledTime: '18:00:00', appliesOnDays: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
    ));
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $hostel->id, termId: $f['term']->id, rollDate: now(),
    ));

    $sickRecord = RollCallRecord::where('roll_call_id', $rollCall->id)->where('student_id', $sickStudent->id)->first();
    $hospitalRecord = RollCallRecord::where('roll_call_id', $rollCall->id)->where('student_id', $hospitalStudent->id)->first();

    expect($sickRecord?->status)->toBe('sick_bay')
        ->and($hospitalRecord?->status)->toBe('hospital');
});

it('supplies BRD-01 a ground-floor constraint without exposing the diagnosis', function (): void {
    $f = brd06Fixture();
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => 'female']);

    $groundRoom = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id, 'is_ground_floor' => true]);
    $groundBed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $groundRoom->id]);

    $upperRoom = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id, 'is_ground_floor' => false]);
    HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $upperRoom->id]);

    $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'female', 'grade_level_id' => $f['gradeLevel']->id]);

    app(DeclareMedicalConditionAction::class)->execute(new DeclareMedicalConditionData(
        schoolId: $f['school']->id, studentId: $student->id, conditionType: 'disability',
        name: 'Mobility impairment requiring ground-floor access', severity: 'moderate', declaredBy: 'guardian',
        effectiveFrom: now(), affectsAccommodation: true, accommodationRequirement: 'Ground floor room required',
    ));

    $allocation = app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $student->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        effectiveFrom: now(), allocatedByUserId: $f['user']->id, asDraft: false,
    ));

    expect($allocation->bed_id)->toBe($groundBed->id);
});
