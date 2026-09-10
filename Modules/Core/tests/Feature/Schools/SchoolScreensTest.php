<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Livewire\Houses\Index as HousesIndex;
use Modules\Core\Livewire\ModuleEntitlement\Index as ModuleEntitlementIndex;
use Modules\Core\Livewire\Schools\Branding;
use Modules\Core\Livewire\Schools\CloneConfig;
use Modules\Core\Livewire\Schools\Index as SchoolsIndex;
use Modules\Core\Livewire\Schools\Profile;
use Modules\Core\Livewire\Schools\Users;
use Modules\Core\Livewire\Structure\Manager;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Tenant;

function assignedSchoolFor(User $user): School
{
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    return $school;
}

it('every per-school screen refuses a user not assigned to that school', function (): void {
    $school = School::factory()->create();
    $stranger = User::factory()->create();

    foreach ([Profile::class, Branding::class, Users::class, CloneConfig::class, Manager::class, HousesIndex::class, ModuleEntitlementIndex::class] as $component) {
        // abort_unless() inside mount() doesn't propagate as a catchable
        // exception here: Livewire's Testable routes it through the same
        // exception-to-response pipeline a real HTTP request would use,
        // so the result is an ordinary (403) TestResponse — assertable
        // exactly like Laravel's own HTTP test assertions, proxied via
        // Testable::__call() to $this->lastState->getResponse().
        Livewire::actingAs($stranger)->test($component, ['school' => $school])->assertForbidden();
    }
});

it('lists the schools the current user is assigned to and creates a new one', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolFor($user);

    Livewire::actingAs($user)
        ->test(SchoolsIndex::class)
        ->assertSee($school->name)
        ->set('code', 'NEW')
        ->set('name', 'New Campus')
        ->set('category', 'private')
        ->call('create')
        ->assertHasNoErrors();

    $created = School::where('code', 'NEW')->sole();
    expect($user->fresh()->isAssignedToSchool($created->id))->toBeTrue();
});

it('updates the school profile but never accepts a code change', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolFor($user);

    Livewire::actingAs($user)
        ->test(Profile::class, ['school' => $school])
        ->set('name', 'Renamed School')
        ->set('province', 'Bulawayo')
        ->call('save')
        ->assertHasNoErrors();

    expect($school->fresh())
        ->name->toBe('Renamed School')
        ->province->toBe('Bulawayo')
        ->code->toBe($school->code);
});

it('updates branding colours', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolFor($user);

    Livewire::actingAs($user)
        ->test(Branding::class, ['school' => $school])
        ->set('primaryColour', '#123456')
        ->call('save')
        ->assertHasNoErrors();

    expect($school->fresh()->primary_colour)->toBe('#123456');
});

it('assigns an existing user by email and reports one that does not exist', function (): void {
    $admin = User::factory()->create();
    $school = assignedSchoolFor($admin);
    $newUser = User::factory()->create();

    $component = Livewire::actingAs($admin)->test(Users::class, ['school' => $school]);

    $component->set('email', 'nobody@example.test')->call('assign')->assertHasErrors(['email']);

    $component->set('email', $newUser->email)->call('assign')->assertHasNoErrors();

    expect($newUser->fresh()->isAssignedToSchool($school->id))->toBeTrue();
});

it('creates sections, grade levels, and classes from the structure manager', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolFor($user);
    AcademicYear::factory()->for($school)->current()->create();

    $component = Livewire::actingAs($user)->test(Manager::class, ['school' => $school]);

    $component->set('sectionCode', 'JUN')->set('sectionName', 'Junior')->set('sectionType', 'primary')
        ->call('createSection')->assertHasNoErrors();

    $section = SchoolSection::where('school_id', $school->id)->where('code', 'JUN')->sole();

    $component->call('openGradeLevelModal', $section->id)
        ->set('gradeLevelCode', 'G3')->set('gradeLevelName', 'Grade 3')->set('gradeLevelOrdinal', 3)
        ->call('createGradeLevel')->assertHasNoErrors();

    $gradeLevel = GradeLevel::where('school_id', $school->id)->where('code', 'G3')->sole();

    $component->call('openClassModal', $gradeLevel->id)
        ->set('classCode', 'G3B')->set('className', 'Grade 3 Blue')
        ->call('createClass')->assertHasNoErrors();

    $component->assertSee('Grade 3 Blue');
});

it('creates a house', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolFor($user);

    Livewire::actingAs($user)
        ->test(HousesIndex::class, ['school' => $school])
        ->set('code', 'CHI')
        ->set('name', 'Chitepo')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('Chitepo');
});

it('toggles a module and reports a blocked dependency as a toast rather than a crash', function (): void {
    config(['core.module_dependencies' => ['BRD' => ['CORE']]]);

    $user = User::factory()->create();
    $school = assignedSchoolFor($user);

    Livewire::actingAs($user)
        ->test(ModuleEntitlementIndex::class, ['school' => $school])
        ->call('toggle', 'BRD', true)
        ->assertDispatched('toast');
});

it('clones another assigned school\'s structure into this one', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $source = School::factory()->for($tenant)->create();
    $target = School::factory()->for($tenant)->create();
    $user->schools()->attach($source, ['is_primary' => true, 'status' => 'active']);
    $user->schools()->attach($target, ['is_primary' => false, 'status' => 'active']);

    SchoolSection::factory()->for($source)->create(['code' => 'JUN']);

    Livewire::actingAs($user)
        ->test(CloneConfig::class, ['school' => $target])
        ->set('sourceSchoolId', $source->id)
        ->call('clone')
        ->assertHasNoErrors();

    expect(SchoolSection::withoutGlobalScopes()->where('school_id', $target->id)->where('code', 'JUN')->exists())->toBeTrue();
});
