<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Livewire\SchoolSwitcher;
use Modules\Core\Livewire\SessionSwitcher;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

/**
 * `SchoolSwitcher`/`SessionSwitcher` (2026-09-12 bugfix, user-reported:
 * "sometimes working sometimes not ... just blinking but nothing
 * changes"): `$this->redirect(Referer)` is replaced with an
 * unconditional `$this->js('window.location.reload()')` plus a
 * session-flashed toast, since a redirect depends on a `Referer` header
 * the test client (and, per the report, real browsers sometimes) never
 * reliably sends.
 */
it('switches the active school with a hard reload, not a redirect', function (): void {
    $user = User::factory()->create();
    $schoolA = School::factory()->create(['name' => 'Alpha High']);
    $schoolB = School::factory()->create(['name' => 'Beta High']);
    $user->schools()->attach($schoolA, ['is_primary' => true, 'status' => 'active']);
    $user->schools()->attach($schoolB, ['is_primary' => false, 'status' => 'active']);

    Livewire::actingAs($user)
        ->test(SchoolSwitcher::class)
        ->call('switchTo', $schoolB->id)
        ->assertJs('window.location.reload()');

    expect(UserSessionPreference::where('user_id', $user->id)->where('school_id', $schoolB->id)->exists())->toBeTrue();
});

it('switches the active session with a hard reload, not a redirect', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    $year = AcademicYear::factory()->for($school)->create(['name' => '2026']);
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['name' => 'Term 2', 'number' => 2]);

    Livewire::actingAs($user)
        ->test(SessionSwitcher::class)
        ->call('switchTo', $year->id, $term->id)
        ->assertJs('window.location.reload()');

    $preference = UserSessionPreference::where('user_id', $user->id)->where('school_id', $school->id)->sole();
    expect($preference->academic_year_id)->toBe($year->id)
        ->and($preference->term_id)->toBe($term->id);
});
