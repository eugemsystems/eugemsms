<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Files\AccessLog;
use Modules\Core\Livewire\Files\Categories;
use Modules\Core\Livewire\Files\Index;
use Modules\Core\Livewire\Files\Quota;
use Modules\Core\Models\File;
use Modules\Core\Models\FileAccessLogEntry;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\StorageQuota;

function loadFileRoutesForTest(): void
{
    if (! Route::has('files.index')) {
        require base_path('Modules/Core/routes/files.php');
    }
}

function grantFilePermissions(User $user, School $school, string ...$permissions): void
{
    loadFileRoutesForTest();

    $grants = array_map(function (string $permission): PermissionGrantData {
        $model = Permission::firstOrCreate(
            ['name' => $permission],
            ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'file', 'action' => last(explode('.', $permission))],
        );

        return new PermissionGrantData($model->id, PermissionScope::School);
    }, $permissions);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: $grants,
    ));
}

function adminForFileTest(School $school): User
{
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);

    return $admin;
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('lists files and refuses without core.file.view', function (): void {
    $school = School::factory()->create();
    $admin = adminForFileTest($school);

    Livewire::actingAs($admin)->test(Index::class, ['school' => $school])->assertForbidden();

    grantFilePermissions($admin, $school, 'core.file.view');
    $file = File::factory()->create(['school_id' => $school->id, 'original_name' => 'report.pdf', 'scan_status' => 'clean']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->assertSee('report.pdf');
});

it('downloads a non-sensitive, clean file', function (): void {
    $school = School::factory()->create();
    $admin = adminForFileTest($school);
    grantFilePermissions($admin, $school, 'core.file.view');

    $file = File::factory()->create([
        'school_id' => $school->id,
        'scan_status' => 'clean',
        'is_sensitive' => false,
        'path' => "school/{$school->id}/signature/test.png",
    ]);
    Storage::disk('local')->put($file->path, 'file contents');

    Livewire::actingAs($admin);
    $component = new Index;
    $component->mount($school);
    $response = $component->download($file->id);

    expect($response)->not->toBeNull();
});

it('refuses to download a sensitive file without core.file.view_sensitive, and permits it once granted', function (): void {
    $school = School::factory()->create();
    $admin = adminForFileTest($school);
    grantFilePermissions($admin, $school, 'core.file.view');

    $file = File::factory()->create([
        'school_id' => $school->id,
        'scan_status' => 'clean',
        'is_sensitive' => true,
        'category' => 'medical_report',
        'path' => "school/{$school->id}/medical_report/test.pdf",
    ]);
    Storage::disk('local')->put($file->path, 'sensitive contents');

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->call('download', $file->id)
        ->assertForbidden();

    grantFilePermissions($admin, $school, 'core.file.view', 'core.file.view_sensitive');

    $component = new Index;
    $component->mount($school);
    $response = $component->download($file->id);

    expect($response)->not->toBeNull();
    $this->assertDatabaseHas('file_access_log', ['file_id' => $file->id, 'user_id' => $admin->id, 'action' => 'download']);
});

it('deletes a file only with core.file.delete', function (): void {
    $school = School::factory()->create();
    $admin = adminForFileTest($school);
    grantFilePermissions($admin, $school, 'core.file.view');

    $file = File::factory()->create(['school_id' => $school->id]);

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->call('delete', $file->id)
        ->assertForbidden();

    grantFilePermissions($admin, $school, 'core.file.view', 'core.file.delete');

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->call('delete', $file->id)
        ->assertDispatched('toast');

    expect($file->fresh()->trashed())->toBeTrue();
});

it('lists registered file categories', function (): void {
    $school = School::factory()->create();
    $admin = adminForFileTest($school);
    grantFilePermissions($admin, $school, 'core.file.view');

    Livewire::actingAs($admin)
        ->test(Categories::class, ['school' => $school])
        ->assertSee('learner_photo')
        ->assertSee('medical_report');
});

it('lists file access log entries scoped to the school, gated by core.file.view_access_log', function (): void {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $admin = adminForFileTest($school);

    $file = File::factory()->create(['school_id' => $school->id, 'original_name' => 'medical.pdf']);
    $otherFile = File::factory()->create(['school_id' => $otherSchool->id, 'original_name' => 'unrelated.pdf']);

    FileAccessLogEntry::create(['file_id' => $file->id, 'user_id' => $admin->id, 'action' => 'download', 'accessed_at' => now()]);
    FileAccessLogEntry::create(['file_id' => $otherFile->id, 'user_id' => $admin->id, 'action' => 'download', 'accessed_at' => now()]);

    Livewire::actingAs($admin)->test(AccessLog::class, ['school' => $school])->assertForbidden();

    grantFilePermissions($admin, $school, 'core.file.view_access_log');

    Livewire::actingAs($admin)
        ->test(AccessLog::class, ['school' => $school])
        ->assertSee('medical.pdf')
        ->assertDontSee('unrelated.pdf');
});

it('shows storage quota usage and lets an admin set a new quota', function (): void {
    $school = School::factory()->create();
    $admin = adminForFileTest($school);
    grantFilePermissions($admin, $school, 'core.file.manage_quota');

    StorageQuota::factory()->create(['school_id' => $school->id, 'quota_bytes' => 5 * 1024 ** 3, 'used_bytes' => 1024 ** 3]);

    Livewire::actingAs($admin)
        ->test(Quota::class, ['school' => $school])
        ->call('openEditModal')
        ->set('quotaGb', '20')
        ->set('warnAtPercent', 90)
        ->call('save')
        ->assertDispatched('toast');

    $quota = StorageQuota::where('school_id', $school->id)->sole();
    expect($quota->quota_bytes)->toBe((int) round(20 * 1024 ** 3))
        ->and($quota->warn_at_percent)->toBe(90);
});
