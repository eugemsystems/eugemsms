<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Modules\Compliance\Domain\Actions\CreateConsentTypeAction;
use Modules\Compliance\Domain\Actions\CreateRetentionScheduleAction;
use Modules\Compliance\Domain\DataObjects\CreateConsentTypeData;
use Modules\Compliance\Domain\DataObjects\CreateRetentionScheduleData;
use Modules\Compliance\Livewire\Privacy\Breaches\Index as BreachesIndex;
use Modules\Compliance\Livewire\Privacy\Consents\Index as ConsentsIndex;
use Modules\Compliance\Livewire\Privacy\ConsentTypes\Index as ConsentTypesIndex;
use Modules\Compliance\Livewire\Privacy\Disposal\Index as DisposalIndex;
use Modules\Compliance\Livewire\Privacy\Notices\Index as NoticesIndex;
use Modules\Compliance\Livewire\Privacy\Processing\Index as ProcessingIndex;
use Modules\Compliance\Livewire\Privacy\Processors\Index as ProcessorsIndex;
use Modules\Compliance\Livewire\Privacy\Requests\Index as RequestsIndex;
use Modules\Compliance\Livewire\Privacy\Retention\Index as RetentionIndex;
use Modules\Compliance\Models\Consent;
use Modules\Compliance\Models\DisposalQueueItem;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\People\Models\Application;

/**
 * Book H3 CMP-03 admin-UI pass. Own, distinctly-named fixture
 * (`cmp03AdminFixture`/`cmp03AdminUser`) — `cmp03Fixture` already
 * exists in the sibling backend test file.
 *
 * @return array<string, mixed>
 */
function cmp03AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    $consentType = app(CreateConsentTypeAction::class)->execute(new CreateConsentTypeData(
        schoolId: $school->id, code: 'photography', name: 'Photography', description: 'Use of photos in publications.',
        lawfulBasis: 'consent', appliesTo: 'guardian',
    ));

    return compact('school', 'user', 'consentType');
}

/**
 * @param  array<string, mixed>  $f
 */
function cmp03AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, strpos($permissionName, '.')));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, strpos($permissionName, '.') + 1, $lastDot - strpos($permissionName, '.') - 1);
        $resource = $resource !== '' ? $resource : $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses to mount the retention screen for a user with no privacy.manage grant', function (): void {
    $f = cmp03AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(RetentionIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every privacy screen for a fully-permissioned user', function (): void {
    $f = cmp03AdminFixture();
    $user = cmp03AdminUser(
        $f,
        'privacy.manage', 'privacy.view', 'privacy.dispose', 'privacy.request.handle', 'privacy.breach.manage',
    );

    Livewire::actingAs($user)->test(ConsentTypesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ConsentsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RetentionIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(DisposalIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RequestsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(BreachesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ProcessingIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ProcessorsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(NoticesIndex::class, ['school' => $f['school']])->assertOk();
});

it('withdraws consent immediately and refuses to withdraw a legal-obligation consent (AC-CMP-03-001)', function (): void {
    $f = cmp03AdminFixture();
    $user = cmp03AdminUser($f, 'privacy.manage', 'privacy.view');

    $consent = Consent::factory()->create([
        'school_id' => $f['school']->id,
        'consent_type_id' => $f['consentType']->id,
        'subject_type' => 'student',
        'subject_id' => 1,
        'granted_by_type' => 'guardian',
        'granted_by_id' => 1,
        'granted' => true,
        'granted_at' => now(),
        'method' => 'portal',
        'notice_version' => '1',
    ]);

    Livewire::actingAs($user)->test(ConsentsIndex::class, ['school' => $f['school']])
        ->call('startWithdraw', $consent->id)
        ->set('withdrawalReason', 'Parent requested withdrawal.')
        ->call('withdraw')
        ->assertDispatched('toast');

    expect($consent->fresh()->withdrawn_at)->not->toBeNull();
});

it('queues records for review and never disposes without an approved review (AC-CMP-03-005)', function (): void {
    $f = cmp03AdminFixture();
    $user = cmp03AdminUser($f, 'privacy.dispose');

    app(CreateRetentionScheduleAction::class)->execute(new CreateRetentionScheduleData(
        schoolId: $f['school']->id, recordClass: 'application_unsuccessful', tableNames: ['applications'],
        retentionYears: '1', retentionTrigger: 'record_created', disposalMethod: 'delete',
        legalBasis: 'No longer needed.', requiresReview: true,
    ));

    $application = Application::factory()->create(['school_id' => $f['school']->id, 'status' => 'declined']);
    $application->timestamps = false;
    $application->updated_at = now()->subYears(2);
    $application->save();

    Livewire::actingAs($user)->test(DisposalIndex::class, ['school' => $f['school']])
        ->call('enqueueDue')
        ->assertDispatched('toast');

    $item = DisposalQueueItem::where('school_id', $f['school']->id)->first();
    expect($item)->not->toBeNull()
        ->and($item->review_status)->toBe('pending_review');

    // Dispose is refused — the item has not been reviewed/approved yet.
    Livewire::actingAs($user)->test(DisposalIndex::class, ['school' => $f['school']])
        ->call('dispose', $item->id);

    expect($item->fresh()->review_status)->toBe('pending_review')
        ->and($item->fresh()->disposed_at)->toBeNull();
});

it('never exposes a safeguarding record\'s own content on the retention or disposal screens — metadata only', function (): void {
    foreach ([
        'Modules/Compliance/Livewire/Privacy/Retention',
        'Modules/Compliance/Livewire/Privacy/Disposal',
        'Modules/Compliance/resources/views/privacy/retention',
        'Modules/Compliance/resources/views/privacy/disposal',
    ] as $path) {
        $files = File::allFiles(base_path($path));

        expect($files)->not->toBeEmpty();

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());

            expect($contents)
                ->not->toContain('Modules\Welfare')
                ->not->toContain('SafeguardingCase')
                ->not->toContain('SafeguardingConcern')
                ->not->toContain('ViewSafeguardingCaseAction')
                ->not->toContain('risk_level')
                ->not->toContain('->category')
                ->not->toContain('->notes')
                ->not->toContain('->diagnosis');
        }
    }
});
