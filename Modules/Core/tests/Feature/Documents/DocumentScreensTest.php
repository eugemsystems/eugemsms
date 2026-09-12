<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Documents\Batches;
use Modules\Core\Livewire\Documents\Index;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentBatch;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

/**
 * See RoleScreensTest.php's identically-named-purpose helper for why
 * routes are loaded directly rather than relying on CoreServiceProvider
 * alone within an isolated test file run.
 */
function loadDocumentRoutesForTest(): void
{
    if (! Route::has('documents.index')) {
        require base_path('Modules/Core/routes/documents.php');
    }
}

/**
 * Accepts one or more permission names and grants them all in a single
 * UpdateUserPermissionsAction call — that action calls
 * $user->syncPermissions(), which REPLACES the whole direct-grant set
 * for this (user, school), not adds to it. Calling this helper twice
 * in a row for the same user/school would silently wipe out the first
 * grant when the second one syncs — pass every permission the test
 * needs in one call instead.
 */
function grantDocumentPermissions(User $user, School $school, string ...$permissions): void
{
    loadDocumentRoutesForTest();

    $grants = array_map(function (string $permission): PermissionGrantData {
        $model = Permission::firstOrCreate(
            ['name' => $permission],
            ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'document', 'action' => last(explode('.', $permission))],
        );

        return new PermissionGrantData($model->id, PermissionScope::School);
    }, $permissions);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: $grants,
    ));
}

it('lists a school\'s generated documents', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantDocumentPermissions($admin, $school, 'core.document.view');

    Document::factory()->for($school)->create(['document_type' => 'receipt', 'number' => 'RCT/000001']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->assertSee('RCT/000001');
});

it('downloads a document and records the download', function (): void {
    Storage::fake('local');

    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantDocumentPermissions($admin, $school, 'core.document.view', 'core.document.download');

    $document = Document::factory()->for($school)->create(['file_path' => 'documents/1/test.html']);
    Storage::disk('local')->put($document->file_path, '<p>content</p>');

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->call('download', $document->id)
        ->assertFileDownloaded();

    expect($document->fresh()->download_count)->toBe(1);
});

it('does not download a document belonging to another school', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantDocumentPermissions($admin, $school, 'core.document.view', 'core.document.download');

    $document = Document::factory()->for($otherSchool)->create();

    expect(fn () => Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->call('download', $document->id)
    )->toThrow(ModelNotFoundException::class);
});

it('queues a document batch', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantDocumentPermissions($admin, $school, 'core.document.generate');

    $template = DocumentTemplate::factory()->for($school)->create(['template_type' => 'receipt']);

    Livewire::actingAs($admin)
        ->test(Batches::class, ['school' => $school])
        ->call('openCreateModal')
        ->set('documentType', 'receipt')
        ->set('templateId', $template->id)
        ->set('totalCount', 25)
        ->call('create')
        ->assertHasNoErrors();

    expect(DocumentBatch::where('school_id', $school->id)->where('total_count', 25)->where('status', 'queued')->exists())->toBeTrue();
});
