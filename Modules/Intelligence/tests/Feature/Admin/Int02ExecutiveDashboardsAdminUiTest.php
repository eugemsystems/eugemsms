<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\Intelligence\Domain\Actions\SetKpiTargetAction;
use Modules\Intelligence\Livewire\Executive\BoardPack;
use Modules\Intelligence\Livewire\Executive\BursarDashboard;
use Modules\Intelligence\Livewire\Executive\HeadDashboard;
use Modules\Intelligence\Livewire\Executive\Kpis;
use Modules\Intelligence\Models\BoardPack as BoardPackRecord;
use Modules\Intelligence\Models\ExecutiveDigest;
use Modules\Intelligence\Models\KpiTarget;
use Modules\Intelligence\Models\WarehouseSnapshot;
use Modules\People\Models\Student;

/**
 * Book J INT-02 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function int02AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create(['is_current' => true]);

    return compact('school', 'year');
}

/**
 * @param  array<string, mixed>  $f
 */
function int02AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $action = end($parts);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => count($parts) > 2 ? $parts[1] : $action, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses every INT-02 screen to a user without its permission', function (string $component): void {
    $f = int02AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'head' => HeadDashboard::class,
    'bursar' => BursarDashboard::class,
    'kpis' => Kpis::class,
    'board pack' => BoardPack::class,
]);

it('renders every INT-02 screen for a fully-permissioned user', function (): void {
    $f = int02AdminFixture();
    $user = int02AdminUser($f, 'executive.dashboard.view', 'executive.dashboard.view.finance', 'executive.kpi.manage', 'executive.board_pack.generate');

    foreach ([HeadDashboard::class, BursarDashboard::class, Kpis::class, BoardPack::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('keeps the head and bursar dashboards on separate permissions', function (): void {
    $f = int02AdminFixture();
    $head = int02AdminUser($f, 'executive.dashboard.view');
    $bursar = int02AdminUser($f, 'executive.dashboard.view.finance');

    Livewire::actingAs($head)->test(BursarDashboard::class, ['school' => $f['school']])->assertForbidden();
    Livewire::actingAs($bursar)->test(HeadDashboard::class, ['school' => $f['school']])->assertForbidden();
});

it('colours a KPI red once it is below its warning threshold, not merely a number (AC-INT-02-001)', function (): void {
    $f = int02AdminFixture();
    $head = int02AdminUser($f, 'executive.dashboard.view');
    Invoice::factory()->for($f['school'])->create(['net_minor' => 10000, 'paid_minor' => 8700, 'balance_minor' => 1300]);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($head)->test(HeadDashboard::class, ['school' => $f['school']]);
    $collection = collect($component->viewData('tiles'))->firstWhere('key', 'collection_rate');

    expect($collection['current'])->toBe(87.0)->and($collection['target'])->toBe(92.0)->and($collection['status'])->toBe('red');
    $component->assertSee('Fee Collection Rate')->assertSee('off target')->assertSee('border-danger');
});

it('links a KPI to the owning module’s own report rather than a parallel screen (BR-INT-02-007)', function (): void {
    $f = int02AdminFixture();
    $head = int02AdminUser($f, 'executive.dashboard.view');
    SchoolContext::set($f['school']);

    $tiles = collect(Livewire::actingAs($head)->test(HeadDashboard::class, ['school' => $f['school']])->viewData('tiles'));

    expect($tiles->firstWhere('key', 'collection_rate')['url'])->toBe(route('finance.reports.collections', $f['school']));
});

it('shows the bursar only finance indicators', function (): void {
    $f = int02AdminFixture();
    $bursar = int02AdminUser($f, 'executive.dashboard.view.finance');
    SchoolContext::set($f['school']);

    $tiles = Livewire::actingAs($bursar)->test(BursarDashboard::class, ['school' => $f['school']])->viewData('tiles');

    expect(collect($tiles)->pluck('key')->all())->toContain('collection_rate')
        ->and(collect($tiles)->every(fn (array $tile): bool => in_array($tile['key'], ['collection_rate', 'average_days_overdue'], true)))->toBeTrue();
});

it('reads the enrolment comparative from warehouse snapshots, not a live count (AC-INT-02-004)', function (): void {
    $f = int02AdminFixture();
    $head = int02AdminUser($f, 'executive.dashboard.view');
    Student::factory()->for($f['school'])->count(5)->create(['status' => 'active']);
    WarehouseSnapshot::create(['school_id' => $f['school']->id, 'entity_key' => 'student', 'snapshot_date' => now()->subYear()->startOfYear()->addMonth(), 'row_count' => 2, 'rebuilt_at' => now(), 'duration_ms' => 5]);
    SchoolContext::set($f['school']);

    $comparative = collect(Livewire::actingAs($head)->test(HeadDashboard::class, ['school' => $f['school']])->viewData('comparative'));

    expect($comparative->firstWhere('year', (int) now()->subYear()->year)['studentCount'])->toBe(2)
        ->and($comparative->firstWhere('year', (int) now()->year)['studentCount'])->toBeNull();
});

it('sends an exceptions-only digest to the signed-in head (BR-INT-02-004)', function (): void {
    $f = int02AdminFixture();
    $head = int02AdminUser($f, 'executive.dashboard.view');
    Invoice::factory()->for($f['school'])->create(['net_minor' => 10000, 'paid_minor' => 5000, 'balance_minor' => 5000]);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($head)->test(HeadDashboard::class, ['school' => $f['school']])->call('sendDigest')->assertDispatched('toast');

    $digest = ExecutiveDigest::where('recipient_user_id', $head->id)->firstOrFail();
    expect($digest->content_summary['all_green'])->toBeFalse()
        ->and(collect($digest->content_summary['exceptions'])->pluck('key')->all())->toContain('collection_rate');
    $component->assertSee('needs attention');
});

it('sets a school target that overrides the default and turns the tile green (BR-INT-02-002)', function (): void {
    $f = int02AdminFixture();
    $manager = int02AdminUser($f, 'executive.kpi.manage', 'executive.dashboard.view');
    $head = int02AdminUser($f, 'executive.dashboard.view');
    Invoice::factory()->for($f['school'])->create(['net_minor' => 10000, 'paid_minor' => 6000, 'balance_minor' => 4000]);
    SchoolContext::set($f['school']);

    Livewire::actingAs($manager)->test(Kpis::class, ['school' => $f['school']])
        ->set('rows.collection_rate.target', '50')->set('rows.collection_rate.warning', '40')
        ->call('save', 'collection_rate')->assertHasNoErrors();

    $override = KpiTarget::where('school_id', $f['school']->id)->where('kpi_key', 'collection_rate')->firstOrFail();
    expect((float) $override->target_value)->toBe(50.0)->and($override->academic_year_id)->toBe($f['year']->id);

    $tile = collect(Livewire::actingAs($head)->test(HeadDashboard::class, ['school' => $f['school']])->viewData('tiles'))->firstWhere('key', 'collection_rate');
    expect($tile['status'])->toBe('green');
});

it('rejects a non-numeric or negative target and an unregistered KPI key', function (): void {
    $f = int02AdminFixture();
    $manager = int02AdminUser($f, 'executive.kpi.manage');

    $component = Livewire::actingAs($manager)->test(Kpis::class, ['school' => $f['school']])
        ->set('rows.collection_rate.target', 'abc')->call('save', 'collection_rate')->assertHasErrors(['rows.collection_rate.target'])
        ->set('rows.collection_rate.target', '-5')->call('save', 'collection_rate')->assertHasErrors(['rows.collection_rate.target']);

    $component->call('save', 'no_such_kpi')->assertNotFound();
    expect(KpiTarget::count())->toBe(0);
});

it('generates a board pack with the financial section alongside the others (AC-INT-02-003)', function (): void {
    Storage::fake();
    $f = int02AdminFixture();
    $generator = int02AdminUser($f, 'executive.board_pack.generate');
    $term = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create();
    Student::factory()->for($f['school'])->count(3)->create(['status' => 'active']);
    SchoolContext::set($f['school']);

    Livewire::actingAs($generator)->test(BoardPack::class, ['school' => $f['school']])
        ->set('termId', $term->id)->call('generate')->assertHasNoErrors()->assertSee('Enrolment');

    $pack = BoardPackRecord::where('school_id', $f['school']->id)->firstOrFail();
    $file = File::findOrFail($pack->document_id);
    $contents = json_decode((string) Storage::disk($file->disk)->get($file->path), true);

    expect($pack->sections_included)->toBe(['enrolment', 'financial', 'staffing', 'boarding', 'collection_rate', 'key_ratios'])
        ->and($contents['sections']['enrolment']['active_students'])->toBe(3)
        ->and($contents['sections']['financial'])->toHaveKeys(['lines', 'net_minor'])
        ->and($contents['sections']['collection_rate'])->toHaveKeys(['currency', 'billed_minor', 'paid_minor', 'rate_percent'])
        ->and($contents['sections']['key_ratios'])->toHaveKeys(['operating_margin_percent', 'staff_cost_percent', 'asset_to_liability_ratio', 'collection_rate_percent']);
});

it('refuses a section with no resolver and a term from another school', function (): void {
    Storage::fake();
    $f = int02AdminFixture();
    $generator = int02AdminUser($f, 'executive.board_pack.generate');
    $term = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create();
    $foreignSchool = School::factory()->create();
    $foreignTerm = Term::factory()->for($foreignSchool)->for(AcademicYear::factory()->for($foreignSchool)->create(), 'academicYear')->create();
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($generator)->test(BoardPack::class, ['school' => $f['school']]);

    $component->set('termId', $term->id)->set('sections', ['risk_summary'])->call('generate')->assertHasErrors(['sections.0']);
    expect(fn () => $component->set('sections', ['enrolment'])->set('termId', $foreignTerm->id)->call('generate'))->toThrow(ModelNotFoundException::class);
    expect(BoardPackRecord::count())->toBe(0);
});

it('refuses a target for a KPI that is not registered, at the Action as well as the screen', function (): void {
    $f = int02AdminFixture();

    expect(fn () => app(SetKpiTargetAction::class)->execute($f['school']->id, 'no_such_kpi', $f['year']->id, 10.0))
        ->toThrow(InvalidArgumentException::class);
    expect(KpiTarget::count())->toBe(0);
});
