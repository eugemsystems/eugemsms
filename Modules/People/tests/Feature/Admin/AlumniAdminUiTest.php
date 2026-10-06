<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\SchemeBudgetEnvelope;
use Modules\People\Livewire\Alumni\Campaigns\Index as CampaignsIndex;
use Modules\People\Livewire\Alumni\Directory\Index as DirectoryIndex;
use Modules\People\Livewire\Alumni\Directory\Show as DirectoryShow;
use Modules\People\Livewire\Alumni\Donations\Record as DonationsRecord;
use Modules\People\Livewire\Alumni\Endowments\Index as EndowmentsIndex;
use Modules\People\Livewire\Alumni\Events\Index as EventsIndex;
use Modules\People\Livewire\Alumni\Pledges\Index as PledgesIndex;
use Modules\People\Models\AlumniCareerUpdate;
use Modules\People\Models\AlumniEvent;
use Modules\People\Models\Alumnus;
use Modules\People\Models\BursaryEndowment;
use Modules\People\Models\CapitalCampaign;
use Modules\People\Models\Donation;
use Modules\People\Models\Pledge;
use Modules\People\Models\Student;

/**
 * Book K PPL-06 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function ppl06AdminFixture(): array
{
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id, 'base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create(['is_current' => true]);
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);
    $student = Student::factory()->for($school)->create(['first_name' => 'Rudo', 'last_name' => 'Chinembiri']);
    $alumnus = Alumnus::factory()->for($school)->create([
        'student_id' => $student->id, 'graduation_year' => 2015, 'admission_number' => 'ADM-2015-001', 'status' => 'active',
        'academic_summary_snapshot' => ['terms' => [['term_id' => $term->id, 'academic_year_id' => $year->id, 'average_percent' => '71.50', 'class_position' => 3, 'class_size' => 30, 'subjects_taken' => 8, 'subjects_passed' => 8]], 'final_average_percent' => '71.50', 'honours' => [['award_type' => 'academic', 'title' => 'Head Girl Award', 'citation' => null, 'awarded_on' => '2015-11-01']]],
    ]);
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));
    $bank = Account::factory()->for($school)->create();
    $income = Account::factory()->for($school)->income()->create();

    return compact('tenant', 'school', 'year', 'term', 'student', 'alumnus', 'bank', 'income');
}

/**
 * @param  array<string, mixed>  $f
 */
function ppl06AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $name): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

it('refuses every PPL-06 screen to a user without its permission', function (string $component): void {
    $f = ppl06AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']] + ($component === DirectoryShow::class ? ['alumnus' => $f['alumnus']->id] : []))->assertForbidden();
})->with([DirectoryIndex::class, DirectoryShow::class, EventsIndex::class, CampaignsIndex::class, PledgesIndex::class, DonationsRecord::class, EndowmentsIndex::class]);

it('lists alumni by year group and marks anyone who has opted out', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.view');
    $other = Student::factory()->for($f['school'])->create(['first_name' => 'Farai', 'last_name' => 'Optout']);
    Alumnus::factory()->for($f['school'])->create(['student_id' => $other->id, 'graduation_year' => 2018, 'status' => 'opted_out']);

    Livewire::actingAs($user)->test(DirectoryIndex::class, ['school' => $f['school']])
        ->assertSee('Rudo Chinembiri')->assertSee('Farai Optout')->assertSee('Do not contact')
        ->set('graduationYear', 2015)->assertSee('Rudo')->assertDontSee('Farai')
        ->set('graduationYear', null)->set('search', 'Chinembiri')->assertSee('Rudo')->assertDontSee('Farai');
});

it('shows the academic summary exactly as frozen at graduation (AC-PPL-06-002)', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.view');

    Livewire::actingAs($user)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $f['alumnus']->id])
        ->assertSee('71.50')->assertSee('Head Girl Award')->assertSee('3/30');
});

it('does not open an alumnus from another school', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.view');
    $otherSchool = School::factory()->create();
    SchoolContext::set($otherSchool);
    $foreign = Alumnus::factory()->for($otherSchool)->create();
    SchoolContext::set($f['school']);

    expect(fn () => Livewire::actingAs($user)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $foreign->id]))->toThrow(ModelNotFoundException::class);
});

it('records career updates as unverified and lets only a verifier confirm them (BR-PPL-06-004)', function (): void {
    $f = ppl06AdminFixture();
    $viewer = ppl06AdminUser($f, 'alumni.view');
    $verifier = ppl06AdminUser($f, 'alumni.view', 'alumni.career.verify');

    $component = Livewire::actingAs($verifier)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $f['alumnus']->id])
        ->set('title', 'Software engineer')->set('institution', 'Econet')->call('addUpdate')->assertHasNoErrors()->assertSee('Unverified');

    $update = AlumniCareerUpdate::firstOrFail();
    expect($update->verified)->toBeFalse();

    Livewire::actingAs($viewer)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $f['alumnus']->id])->call('verify', $update->id)->assertForbidden();

    Livewire::actingAs($verifier)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $f['alumnus']->id])->call('verify', $update->id);
    expect($update->fresh()->verified)->toBeTrue();
});

it('cannot verify another alumnus’s update, and refuses a career update that ends before it starts', function (): void {
    $f = ppl06AdminFixture();
    $verifier = ppl06AdminUser($f, 'alumni.view', 'alumni.career.verify');
    $otherAlumnus = Alumnus::factory()->for($f['school'])->create(['student_id' => Student::factory()->for($f['school'])->create()->id]);
    $foreign = AlumniCareerUpdate::factory()->for($f['school'])->create(['alumnus_id' => $otherAlumnus->id, 'verified' => false]);

    $component = Livewire::actingAs($verifier)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $f['alumnus']->id]);

    expect(fn () => $component->call('verify', $foreign->id))->toThrow(ModelNotFoundException::class);
    expect($foreign->fresh()->verified)->toBeFalse();

    $component->set('title', 'Studies')->set('startsOn', '2020-05-01')->set('endsOn', '2019-05-01')->call('addUpdate')->assertHasErrors('title');
});

it('records an opt-out, offers no way to opt back in, and creates a portal account in the school’s tenant (AC-PPL-06-005, BR-PPL-06-012)', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.view', 'alumni.contact.manage');

    $component = Livewire::actingAs($user)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $f['alumnus']->id])
        ->set('portalEmail', 'rudo@example.test')->call('offerPortal')->assertHasNoErrors();

    $portal = User::where('email', 'rudo@example.test')->firstOrFail();
    expect($portal->tenant_id)->toBe($f['tenant']->id)->and($f['alumnus']->fresh()->user_id)->toBe($portal->id);

    $component->set('portalEmail', 'second@example.test')->call('offerPortal')->assertHasErrors('portalEmail');

    $component->call('optOut')->assertSee('Only the alumnus can opt back in');
    expect($f['alumnus']->fresh()->status)->toBe('opted_out')->and(method_exists($component->instance(), 'optBackIn'))->toBeFalse();

    expect(DB::table('notification_opt_outs')->where('address', 'rudo@example.test')->exists())->toBeTrue();
});

it('refuses a portal account with a duplicate email', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.view', 'alumni.contact.manage');
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::actingAs($user)->test(DirectoryShow::class, ['school' => $f['school'], 'alumnus' => $f['alumnus']->id])->set('portalEmail', 'taken@example.test')->call('offerPortal')->assertHasErrors('portalEmail');

    expect($f['alumnus']->fresh()->user_id)->toBeNull();
});

it('creates an alumni event on the school calendar and refuses malformed target years', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.event.manage');

    $component = Livewire::actingAs($user)->test(EventsIndex::class, ['school' => $f['school']])
        ->set('title', 'Class of 2015 reunion')->set('startsAt', now()->addMonth()->format('Y-m-d\TH:i'))->set('targetYears', '2015, abc')->call('create')->assertHasErrors('targetYears');
    expect(AlumniEvent::count())->toBe(0);

    $component->set('targetYears', '2015, 2016')->call('create')->assertHasNoErrors()->assertSee('Class of 2015 reunion');

    expect(AlumniEvent::firstOrFail()->target_graduation_years)->toBe([2015, 2016])->and(CalendarEvent::where('title', 'Class of 2015 reunion')->exists())->toBeTrue();
});

it('creates a campaign on the school’s own income account only, with a positive target', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.campaign.manage');
    $foreign = Account::factory()->for(School::factory()->create())->income()->create();
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(CampaignsIndex::class, ['school' => $f['school']])->set('name', 'New Science Block')->set('purpose', 'Build labs')->set('target', '-5')->set('incomeAccountId', $f['income']->id);

    $component->call('create')->assertHasErrors('target');
    expect(CapitalCampaign::count())->toBe(0);

    expect(fn () => $component->set('target', '50000')->set('incomeAccountId', $foreign->id)->call('create'))->toThrow(ModelNotFoundException::class);

    $component->set('incomeAccountId', $f['income']->id)->call('create')->assertHasNoErrors();
    expect(CapitalCampaign::firstOrFail())->target_amount_minor->toBe(5000000)->raised_amount_minor->toBe(0);
});

it('records pledges only to an active campaign in its own currency, and never counts them as income (BR-PPL-06-006)', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.pledge.manage');
    $campaign = CapitalCampaign::factory()->for($f['school'])->create(['income_account_id' => $f['income']->id, 'currency' => 'USD', 'status' => 'active']);
    $closed = CapitalCampaign::factory()->for($f['school'])->create(['income_account_id' => $f['income']->id, 'currency' => 'USD', 'status' => 'closed']);

    $component = Livewire::actingAs($user)->test(PledgesIndex::class, ['school' => $f['school']])->set('donorName', 'Jane Alumna')->set('amount', '5000')->set('campaignId', $campaign->id);

    $component->call('create')->assertHasNoErrors();
    expect(Pledge::firstOrFail())->paid_to_date_minor->toBe(0)->status->toBe('pledged')->and($campaign->fresh()->raised_amount_minor)->toBe(0);

    $component->set('donorName', 'Joe')->set('amount', '100')->set('campaignId', $closed->id)->call('create')->assertHasErrors('donorName');
    $component->set('campaignId', $campaign->id)->set('amount', '100')->set('currency', 'ZWG')->call('create')->assertHasErrors('donorName');
    expect(Pledge::count())->toBe(1);
});

it('shows campaign progress from donations actually received, not the pledged total (AC-PPL-06-003)', function (): void {
    $f = ppl06AdminFixture();
    $recorder = ppl06AdminUser($f, 'alumni.donation.record');
    $viewer = ppl06AdminUser($f, 'alumni.campaign.manage');
    $campaign = CapitalCampaign::factory()->for($f['school'])->create(['name' => 'Appeal', 'income_account_id' => $f['income']->id, 'currency' => 'USD', 'status' => 'active', 'target_amount_minor' => 1000000, 'raised_amount_minor' => 0]);
    $pledge = Pledge::factory()->for($f['school'])->create(['campaign_id' => $campaign->id, 'currency' => 'USD', 'pledged_amount_minor' => 500000, 'paid_to_date_minor' => 0, 'status' => 'pledged']);

    Livewire::actingAs($recorder)->test(DonationsRecord::class, ['school' => $f['school']])
        ->set('donorName', 'Jane Alumna')->set('amount', '1200')->set('bankAccountId', $f['bank']->id)->set('pledgeId', $pledge->id)->call('record')->assertHasNoErrors();

    expect($campaign->fresh()->raised_amount_minor)->toBe(120000)->and($pledge->fresh())->paid_to_date_minor->toBe(120000)->status->toBe('fulfilling')
        ->and(Donation::firstOrFail()->journal_id)->not->toBeNull();

    Livewire::actingAs($viewer)->test(CampaignsIndex::class, ['school' => $f['school']])->assertSee('1,200.00')->assertSee('5,000.00');
});

it('refuses donations with bad figures, a foreign bank account, a lapsed pledge, a closed campaign or a restricted gift with no purpose', function (): void {
    $f = ppl06AdminFixture();
    $user = ppl06AdminUser($f, 'alumni.donation.record');
    $foreignBank = Account::factory()->for(School::factory()->create())->create();
    SchoolContext::set($f['school']);
    $lapsed = Pledge::factory()->for($f['school'])->create(['currency' => 'USD', 'status' => 'lapsed']);
    $closed = CapitalCampaign::factory()->for($f['school'])->create(['income_account_id' => $f['income']->id, 'currency' => 'USD', 'status' => 'closed']);

    $component = Livewire::actingAs($user)->test(DonationsRecord::class, ['school' => $f['school']])->set('donorName', 'Donor')->set('amount', '100')->set('bankAccountId', $f['bank']->id)->set('incomeAccountId', $f['income']->id);

    $component->set('pledgeId', $lapsed->id)->call('record')->assertHasErrors('donorName');
    $component->set('pledgeId', null)->set('campaignId', $closed->id)->call('record')->assertHasErrors('donorName');
    $component->set('campaignId', null)->set('isRestricted', true)->set('restrictionPurpose', '')->call('record')->assertHasErrors('donorName');
    $component->set('isRestricted', false)->set('currency', 'XXX')->call('record')->assertHasErrors('donorName');

    expect(fn () => $component->set('currency', 'USD')->set('bankAccountId', $foreignBank->id)->call('record'))->toThrow(ModelNotFoundException::class);
    expect(Donation::count())->toBe(0);
});

it('creates an endowment for the school’s own scheme and tops up the scheme’s envelope as donations arrive (BR-PPL-06-008)', function (): void {
    $f = ppl06AdminFixture();
    $manager = ppl06AdminUser($f, 'alumni.endowment.manage');
    $recorder = ppl06AdminUser($f, 'alumni.donation.record');
    $scheme = DiscountScheme::factory()->for($f['school'])->create(['contra_account_id' => $f['income']->id, 'name' => 'Moyo Bursary']);
    $foreignScheme = DiscountScheme::factory()->for(School::factory()->create())->create();
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($manager)->test(EndowmentsIndex::class, ['school' => $f['school']])->set('donorName', 'The Moyo Family')->set('capital', '1000')->set('namedRecognition', 'The Moyo Family Bursary');

    expect(fn () => $component->set('fundsSchemeId', $foreignScheme->id)->call('create'))->toThrow(ModelNotFoundException::class);

    $component->set('fundsSchemeId', $scheme->id)->call('create')->assertHasNoErrors()->assertSee('Moyo Bursary');
    $endowment = BursaryEndowment::firstOrFail();

    expect(SchemeBudgetEnvelope::where('scheme_id', $scheme->id)->first()?->budget_minor)->toBe(100000);

    Livewire::actingAs($recorder)->test(DonationsRecord::class, ['school' => $f['school']])
        ->set('donorName', 'The Moyo Family')->set('amount', '500')->set('bankAccountId', $f['bank']->id)->set('incomeAccountId', $f['income']->id)->set('endowmentId', $endowment->id)->call('record')->assertHasNoErrors();

    expect(SchemeBudgetEnvelope::where('scheme_id', $scheme->id)->firstOrFail()->budget_minor)->toBe(150000);
});

it('refuses an endowment with no money behind it, and hides an anonymous donor’s name from the recognition line', function (): void {
    $f = ppl06AdminFixture();
    $manager = ppl06AdminUser($f, 'alumni.endowment.manage');
    $scheme = DiscountScheme::factory()->for($f['school'])->create(['contra_account_id' => $f['income']->id]);

    $component = Livewire::actingAs($manager)->test(EndowmentsIndex::class, ['school' => $f['school']])->set('donorName', 'Secret Donor')->set('fundsSchemeId', $scheme->id);

    $component->call('create')->assertHasErrors('donorName');
    expect(BursaryEndowment::count())->toBe(0);

    $component->set('capital', '500')->set('isAnonymous', true)->set('namedRecognition', 'The Anonymous Bursary')->call('create')->assertHasNoErrors();

    expect(BursaryEndowment::firstOrFail()->displayName())->toBe('Anonymous');
    $component->assertSee('Anonymous')->assertDontSee('Secret Donor');
});
