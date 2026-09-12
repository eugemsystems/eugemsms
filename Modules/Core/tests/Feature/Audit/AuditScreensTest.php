<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Audit\RecordActivityAction;
use Modules\Core\Domain\Actions\Audit\RecordDataAccessAction;
use Modules\Core\Domain\Actions\Audit\RecordFinancialAuditEntryAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Audit\RecordActivityData;
use Modules\Core\Domain\DataObjects\Audit\RecordDataAccessData;
use Modules\Core\Domain\DataObjects\Audit\RecordFinancialAuditEntryData;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Audit\AccessLog;
use Modules\Core\Livewire\Audit\Explorer;
use Modules\Core\Livewire\Audit\Export;
use Modules\Core\Livewire\Audit\FinancialStream;
use Modules\Core\Livewire\Audit\Integrity;
use Modules\Core\Livewire\Audit\RecordHistory;
use Modules\Core\Livewire\Audit\SecurityEvents;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\DataAccessLogEntry;
use Modules\Core\Models\IntegrityCheckRun;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SecurityEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

function loadAuditRoutesForTest(): void
{
    if (! Route::has('audit.explorer')) {
        require base_path('Modules/Core/routes/audit.php');
    }
}

function grantAuditPermissions(User $user, School $school, string ...$permissions): void
{
    loadAuditRoutesForTest();

    $grants = array_map(function (string $permission): PermissionGrantData {
        $model = Permission::firstOrCreate(
            ['name' => $permission],
            ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'audit', 'action' => last(explode('.', $permission))],
        );

        return new PermissionGrantData($model->id, PermissionScope::School);
    }, $permissions);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: $grants,
    ));
}

function adminForAuditTest(School $school): User
{
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);

    return $admin;
}

it('lists activity log entries for a school and refuses without core.audit.view', function (): void {
    $school = School::factory()->create();
    $admin = adminForAuditTest($school);

    Livewire::actingAs($admin)->test(Explorer::class, ['school' => $school])->assertForbidden();

    grantAuditPermissions($admin, $school, 'core.audit.view');
    app(RecordActivityAction::class)->execute(new RecordActivityData(
        logName: 'core',
        description: 'created a house',
        schoolId: $school->id,
        subjectType: 'house',
        subjectId: 1,
        event: 'created',
    ));

    Livewire::actingAs($admin)->test(Explorer::class, ['school' => $school])->assertSee('created a house');
});

it('shows a specific record\'s timeline', function (): void {
    $school = School::factory()->create();
    $admin = adminForAuditTest($school);
    grantAuditPermissions($admin, $school, 'core.audit.view');

    app(RecordActivityAction::class)->execute(new RecordActivityData(
        logName: 'core',
        description: 'created a house',
        schoolId: $school->id,
        subjectType: 'house',
        subjectId: 42,
        event: 'created',
    ));

    Livewire::actingAs($admin)
        ->test(RecordHistory::class, ['school' => $school, 'subjectType' => 'house', 'subjectId' => 42])
        ->assertSee('created a house');
});

it('shows the financial audit stream and verifies the chain', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $admin = adminForAuditTest($school);
    grantAuditPermissions($admin, $school, 'core.audit.view_financial');

    app(RecordFinancialAuditEntryAction::class)->execute(new RecordFinancialAuditEntryData(
        schoolId: $school->id,
        eventType: 'receipt_issued',
        subjectType: 'receipt',
        subjectId: 1,
        payload: ['amount' => 100],
        causerId: $admin->id,
        academicYearId: $year->id,
    ));

    Livewire::actingAs($admin)
        ->test(FinancialStream::class, ['school' => $school])
        ->assertSee('Receipt Issued')
        ->call('verifyChain')
        ->assertDispatched('toast', variant: 'success');
});

it('lists and reviews a security event, refusing review without the extra permission', function (): void {
    $school = School::factory()->create();
    $admin = adminForAuditTest($school);
    grantAuditPermissions($admin, $school, 'core.audit.view_security');

    $event = SecurityEvent::factory()->create(['school_id' => $school->id, 'description' => 'Suspicious login pattern']);

    Livewire::actingAs($admin)
        ->test(SecurityEvents::class, ['school' => $school])
        ->assertSee('Suspicious login pattern')
        ->call('openReviewModal', $event->id)
        ->assertForbidden();

    grantAuditPermissions($admin, $school, 'core.audit.view_security', 'core.audit.review_security_event');

    Livewire::actingAs($admin)
        ->test(SecurityEvents::class, ['school' => $school])
        ->call('openReviewModal', $event->id)
        ->set('reviewNotes', 'False positive')
        ->call('review')
        ->assertDispatched('toast');

    expect($event->fresh()->is_reviewed)->toBeTrue();
});

it('lists data access log entries', function (): void {
    $school = School::factory()->create();
    $admin = adminForAuditTest($school);
    grantAuditPermissions($admin, $school, 'core.audit.view_access');

    app(RecordDataAccessAction::class)->execute(new RecordDataAccessData(
        schoolId: $school->id,
        userId: $admin->id,
        accessType: 'view',
        resourceType: 'medical_record',
    ));

    Livewire::actingAs($admin)
        ->test(AccessLog::class, ['school' => $school])
        ->assertSee('Medical Record');
});

it('runs integrity checks from the dashboard', function (): void {
    $school = School::factory()->create();
    $admin = adminForAuditTest($school);
    grantAuditPermissions($admin, $school, 'core.audit.view');

    Livewire::actingAs($admin)
        ->test(Integrity::class, ['school' => $school])
        ->call('runChecks')
        ->assertDispatched('toast');

    expect(IntegrityCheckRun::where('school_id', $school->id)->count())->toBeGreaterThan(0);
});

it('previews the matching record count before exporting', function (): void {
    $school = School::factory()->create();
    $admin = adminForAuditTest($school);
    grantAuditPermissions($admin, $school, 'core.audit.export');

    app(RecordActivityAction::class)->execute(new RecordActivityData(
        logName: 'core',
        description: 'created a house',
        schoolId: $school->id,
        event: 'created',
    ));

    Livewire::actingAs($admin)
        ->test(Export::class, ['school' => $school])
        ->assertSee('1 record');
});

/**
 * Called directly on the component rather than through Livewire::test()
 * — see Documents\Index's own download test for why: combining a
 * full-page #[Layout(...)] render with a file-download effect trips an
 * unrelated snapshot-parsing quirk in Livewire's own testing harness.
 * The export flow itself — the permission gate, the date filter, and
 * RecordDataAccessAction logging the export — is exercised exactly as
 * the real request would run it either way.
 */
it('exports the activity log as csv and logs the export itself', function (): void {
    $school = School::factory()->create();
    $admin = adminForAuditTest($school);
    grantAuditPermissions($admin, $school, 'core.audit.export');
    Livewire::actingAs($admin);

    app(RecordActivityAction::class)->execute(new RecordActivityData(
        logName: 'core',
        description: 'created a house',
        schoolId: $school->id,
        event: 'created',
    ));

    $component = new Export;
    $component->mount($school);

    $response = $component->export();

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and(DataAccessLogEntry::where('school_id', $school->id)->where('resource_type', 'activity_log')->exists())->toBeTrue();
});
