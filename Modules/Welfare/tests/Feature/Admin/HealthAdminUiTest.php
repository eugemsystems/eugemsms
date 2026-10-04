<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\Actions\DeclareMedicalConditionAction;
use Modules\Welfare\Domain\Actions\GrantMedicalConsentAction;
use Modules\Welfare\Domain\DataObjects\DeclareMedicalConditionData;
use Modules\Welfare\Domain\DataObjects\GrantMedicalConsentData;
use Modules\Welfare\Livewire\Health\Alerts;
use Modules\Welfare\Livewire\Health\MedicationRound;
use Modules\Welfare\Livewire\Health\Record;
use Modules\Welfare\Livewire\Health\SickBay;
use Modules\Welfare\Livewire\Health\Stock;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\MedicationAdministration;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * Book G BRD-06 admin-UI pass 🔒. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, student: Student}
 */
function healthAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $student = Student::factory()->for($school)->create();

    // `hasPermissionTo()` throws rather than returning false when the
    // permission row doesn't exist at all yet — pre-create every one
    // `ResolveMedicalTierAction` checks directly, matching every other
    // fixture in this pass.
    Permission::firstOrCreate(['name' => 'health.clinical.view'], ['guard_name' => 'web', 'module_code' => 'HEALTH', 'resource' => 'clinical', 'action' => 'view']);
    Permission::firstOrCreate(['name' => 'health.actionable.view'], ['guard_name' => 'web', 'module_code' => 'HEALTH', 'resource' => 'actionable', 'action' => 'view']);

    return compact('school', 'year', 'term', 'student');
}

/**
 * @param  array<string, mixed>  $f
 */
function healthAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $moduleCode = strtoupper($parts[0]);
        $action = array_pop($parts);
        $resource = implode('.', array_slice($parts, 1)) ?: $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    if ($grants !== []) {
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $user->id, schoolId: $f['school']->id, grants: $grants,
        ));
    }

    return $user;
}

it('refuses Health\\Record (Tier 3) to a viewer who only clears Tier 2 care-responsibility, not health.clinical.view', function (): void {
    $f = healthAdminFixture();
    $user = healthAdminUser($f, 'health.actionable.view');

    Livewire::actingAs($user)->test(Record::class, ['school' => $f['school'], 'student' => $f['student']])
        ->assertForbidden();
});

it('lets a nurse into Health\\Record and never leaks Tier 3 diagnosis detail onto the Tier 2 Alerts board', function (): void {
    $f = healthAdminFixture();
    $nurse = healthAdminUser($f, 'health.clinical.view', 'health.clinical.manage');

    app(DeclareMedicalConditionAction::class)->execute(new DeclareMedicalConditionData(
        schoolId: $f['school']->id,
        studentId: $f['student']->id,
        conditionType: 'allergy',
        name: 'Severe peanut allergy — anaphylaxis risk',
        severity: 'severe',
        declaredBy: 'nurse',
        effectiveFrom: Carbon::now(),
        publicSummary: 'Severe nut allergy — EpiPen',
        affectsDietary: true,
        createdByUserId: $nurse->id,
    ));

    Livewire::actingAs($nurse)->test(Record::class, ['school' => $f['school'], 'student' => $f['student']])
        ->assertOk()
        ->assertSee('Severe peanut allergy — anaphylaxis risk');

    $housemaster = healthAdminUser($f, 'health.actionable.view');

    Livewire::actingAs($housemaster)->test(Alerts::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('Severe nut allergy — EpiPen')
        ->assertDontSee('Severe peanut allergy — anaphylaxis risk');
});

it('refuses to administer controlled medication without a witness, surfaced as a toast, not a server error', function (): void {
    $f = healthAdminFixture();
    $nurse = healthAdminUser($f, 'health.medication.administer');

    $guardian = Guardian::factory()->for($f['school'])->create();
    StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $f['student']->id, 'guardian_id' => $guardian->id, 'status' => 'active', 'may_authorise_medical' => true]);

    app(GrantMedicalConsentAction::class)->execute(new GrantMedicalConsentData(
        schoolId: $f['school']->id, studentId: $f['student']->id, guardianId: $guardian->id,
        consentType: 'otc_medication', granted: true, grantedAt: Carbon::now(), grantedVia: 'form',
        effectiveFrom: Carbon::now()->subDay(),
    ));

    $stock = ClinicStock::factory()->create(['school_id' => $f['school']->id, 'is_controlled' => true, 'quantity_on_hand' => 10]);

    Livewire::actingAs($nurse)->test(MedicationRound::class, ['school' => $f['school']])
        ->set('studentId', $f['student']->id)
        ->set('medicationName', 'Morphine')
        ->set('dose', '5mg')
        ->set('clinicStockId', $stock->id)
        ->call('administer')
        ->assertOk();

    expect(MedicationAdministration::where('student_id', $f['student']->id)->count())->toBe(0);
});

it('admits and discharges through the sick bay screen, closing the sick_bay roll-status window', function (): void {
    $f = healthAdminFixture();
    $nurse = healthAdminUser($f, 'health.admission.manage');

    Livewire::actingAs($nurse)->test(SickBay::class, ['school' => $f['school']])
        ->set('studentId', $f['student']->id)
        ->set('presentingComplaint', 'Headache and fever')
        ->set('severity', 'moderate')
        ->call('admit')
        ->assertOk();

    $admission = SickBayAdmission::where('student_id', $f['student']->id)->first();
    expect($admission)->not->toBeNull()
        ->and(SickBayAdmission::CURRENTLY_ADMITTED_STATUSES)->toContain($admission->status);

    Livewire::actingAs($nurse)->test(SickBay::class, ['school' => $f['school']])
        ->set('dischargeDestination', 'hostel')
        ->call('discharge', $admission->id)
        ->assertOk();

    expect($admission->refresh()->status)->toBe('discharged');
});

it('refuses to receive controlled stock without a second, different witness', function (): void {
    $f = healthAdminFixture();
    $nurse = healthAdminUser($f, 'health.stock.manage', 'health.stock.controlled');
    $stock = ClinicStock::factory()->create(['school_id' => $f['school']->id, 'is_controlled' => true, 'quantity_on_hand' => 5]);

    Livewire::actingAs($nurse)->test(Stock::class, ['school' => $f['school']])
        ->set('quantity', 10)
        ->call('receive', $stock->id)
        ->assertOk();

    expect($stock->refresh()->quantity_on_hand)->toEqual(5.0);
});
