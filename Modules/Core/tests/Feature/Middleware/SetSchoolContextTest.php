<?php

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Http\Middleware\SetSchoolContext;
use Modules\Core\Models\School;
use Modules\Core\Models\UserSessionPreference;

function schoolContextRequestFor(User $user): Request
{
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('resolves the user\'s primary school when there is no stored preference', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    (new SetSchoolContext)->handle(schoolContextRequestFor($user), fn () => response('ok'));

    expect(SchoolContext::currentId())->toBe($school->id);
});

it('honours an explicit X-School-Id header for an assigned school', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user = User::factory()->create();
    $user->schools()->attach($schoolA, ['is_primary' => true, 'status' => 'active']);
    $user->schools()->attach($schoolB, ['is_primary' => false, 'status' => 'active']);

    $request = schoolContextRequestFor($user);
    $request->headers->set('X-School-Id', (string) $schoolB->id);

    (new SetSchoolContext)->handle($request, fn () => response('ok'));

    expect(SchoolContext::currentId())->toBe($schoolB->id);
});

it('refuses an X-School-Id header for a school the user is not assigned to', function (): void {
    $ownSchool = School::factory()->create();
    $otherSchool = School::factory()->create();
    $user = User::factory()->create();
    $user->schools()->attach($ownSchool, ['is_primary' => true, 'status' => 'active']);

    $request = schoolContextRequestFor($user);
    $request->headers->set('X-School-Id', (string) $otherSchool->id);

    (new SetSchoolContext)->handle($request, fn () => response('ok'));
})->throws(UnauthorisedSchoolAccessException::class);

it('resolves the most recently switched-to school when preference rows exist for several', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user = User::factory()->create();
    $user->schools()->attach($schoolA, ['is_primary' => true, 'status' => 'active']);
    $user->schools()->attach($schoolB, ['is_primary' => false, 'status' => 'active']);

    UserSessionPreference::create(['user_id' => $user->id, 'school_id' => $schoolA->id])
        ->forceFill(['updated_at' => now()->subMinute()])->save();
    UserSessionPreference::create(['user_id' => $user->id, 'school_id' => $schoolB->id])
        ->forceFill(['updated_at' => now()])->save();

    (new SetSchoolContext)->handle(schoolContextRequestFor($user), fn () => response('ok'));

    expect(SchoolContext::currentId())->toBe($schoolB->id);
});

it('passes through untouched for an unauthenticated request', function (): void {
    $request = Request::create('/dashboard');

    $response = (new SetSchoolContext)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(SchoolContext::current())->toBeNull();
});
