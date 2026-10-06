<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Approvals\CreateApprovalChainAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Approvals\ApprovalStepData;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Livewire\Discounts\AwardList;
use Modules\Finance\Livewire\Discounts\Budgets;
use Modules\Finance\Livewire\Discounts\ConditionReview;
use Modules\Finance\Livewire\Discounts\GrantAward;
use Modules\Finance\Livewire\Discounts\Schemes;
use Modules\Finance\Livewire\Discounts\SponsorAwards;
use Modules\Finance\Livewire\Reports\Discounts as DiscountsReport;
use Modules\Finance\Livewire\Scholarships\Applications;
use Modules\Finance\Livewire\Scholarships\Committee;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AwardUtilisation;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\Finance\Models\SchemeBudgetEnvelope;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book K FIN-07 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function fin07AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $account = Account::factory()->for($school)->create();
    $student = Student::factory()->for($school)->create(['first_name' => 'Tendai', 'last_name' => 'Moyo']);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('finance.award_approval_threshold_minor', SettingScope::School, $school->id, 100000000));

    return compact('school', 'year', 'term', 'account', 'student');
}

/**
 * @param  array<string, mixed>  $f
 */
function fin07AdminUser(array $f, string ...$permissionNames): User
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

/**
 * @param  array<string, mixed>  $f
 * @param  array<string, mixed>  $overrides
 */
function fin07AdminScheme(array $f, array $overrides = []): DiscountScheme
{
    return DiscountScheme::factory()->for($f['school'])->create($overrides + ['contra_account_id' => $f['account']->id, 'scheme_type' => 'individually_granted', 'category' => 'hardship', 'requires_approval' => false]);
}

it('refuses every FIN-07 screen to a user without its permission', function (string $component): void {
    $f = fin07AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    Schemes::class, Budgets::class, Applications::class, Committee::class, AwardList::class,
    GrantAward::class, ConditionReview::class, SponsorAwards::class, DiscountsReport::class,
]);

it('creates a sibling scheme with tier bands and refuses malformed bands, duplicate codes and a foreign contra account', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.discount_scheme.view', 'finance.discount_scheme.manage');
    $foreignAccount = Account::factory()->for(School::factory()->create())->create();
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(Schemes::class, ['school' => $f['school']])
        ->set('code', 'sibling')->set('name', 'Sibling Discount')->set('schemeType', 'automatic')->set('category', 'sibling')
        ->set('contraAccountId', $f['account']->id)->set('requiresApproval', false);

    $component->set('tierBands', 'second child')->call('create')->assertHasErrors('tierBands');
    $component->set('tierBands', "2=10\n3=15\n4=20")->call('create')->assertHasNoErrors();

    $scheme = DiscountScheme::firstOrFail();

    expect($scheme->code)->toBe('SIBLING')->and($scheme->tier_bands)->toBe([['nth' => 2, 'percent' => '10'], ['nth' => 3, 'percent' => '15'], ['nth' => 4, 'percent' => '20']]);

    $component->set('code', 'SIBLING')->set('name', 'Again')->set('contraAccountId', $f['account']->id)->set('tierBands', '2=10')->call('create')->assertHasErrors('code');

    expect(fn () => Livewire::actingAs($user)->test(Schemes::class, ['school' => $f['school']])
        ->set('code', 'OTHER')->set('name', 'Other')->set('contraAccountId', $foreignAccount->id)->call('create'))->toThrow(ModelNotFoundException::class);
});

it('refuses an automatic scheme that has no eligibility rule, and a viewer cannot manage', function (): void {
    $f = fin07AdminFixture();
    $manager = fin07AdminUser($f, 'finance.discount_scheme.view', 'finance.discount_scheme.manage');
    $viewer = fin07AdminUser($f, 'finance.discount_scheme.view');

    Livewire::actingAs($manager)->test(Schemes::class, ['school' => $f['school']])
        ->set('code', 'MAGIC')->set('name', 'Magic')->set('schemeType', 'automatic')->set('category', 'sport')->set('contraAccountId', $f['account']->id)->call('create')->assertHasErrors('code');

    Livewire::actingAs($viewer)->test(Schemes::class, ['school' => $f['school']])->set('code', 'X')->set('name', 'X')->set('contraAccountId', $f['account']->id)->call('create')->assertForbidden();

    expect(DiscountScheme::count())->toBe(0);
});

it('creates one envelope per scheme and year and refuses a duplicate', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.discount_scheme.manage');
    $scheme = fin07AdminScheme($f);

    $component = Livewire::actingAs($user)->test(Budgets::class, ['school' => $f['school']])->set('schemeId', $scheme->id)->set('budget', '20000')->call('create')->assertHasNoErrors();

    expect(SchemeBudgetEnvelope::firstOrFail()->budget_minor)->toBe(2000000);

    $component->set('schemeId', $scheme->id)->set('budget', '5000')->call('create')->assertHasErrors('schemeId');
    expect(SchemeBudgetEnvelope::count())->toBe(1);
});

it('refuses an award that would exceed a capped envelope, naming the shortfall, and shows it live first (AC-FIN-07-003)', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.award.grant');
    $scheme = fin07AdminScheme($f, ['code' => 'BURSARY']);
    SchemeBudgetEnvelope::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'academic_year_id' => $f['year']->id, 'budget_minor' => 2000000, 'currency' => 'USD', 'committed_minor' => 1950000, 'utilised_minor' => 0]);

    $component = Livewire::actingAs($user)->test(GrantAward::class, ['school' => $f['school']])
        ->call('selectStudent', $f['student']->id)->set('schemeId', $scheme->id)->set('awardMethod', 'fixed_amount')->set('amount', '1000')->set('currency', 'USD')
        ->assertSee('exceeds the envelope by 500.00')->call('grant')->assertHasErrors('schemeId');

    expect(DiscountAward::count())->toBe(0);

    $component->set('amount', '400')->call('grant')->assertHasNoErrors();
    expect(DiscountAward::firstOrFail())->status->toBe('active')->award_amount_minor->toBe(40000);
});

it('will not grant an award for an application-based scheme without an approved application, or twice for the same period', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.award.grant');
    $scheme = fin07AdminScheme($f, ['scheme_type' => 'application_based', 'code' => 'ACAD']);

    $component = Livewire::actingAs($user)->test(GrantAward::class, ['school' => $f['school']])
        ->call('selectStudent', $f['student']->id)->set('schemeId', $scheme->id)->set('percent', '50')->call('grant')->assertHasErrors('schemeId');
    expect(DiscountAward::count())->toBe(0);

    $application = ScholarshipApplication::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id, 'status' => 'approved']);

    $component->set('applicationId', $application->id)->call('grant')->assertHasNoErrors();
    expect(DiscountAward::count())->toBe(1);

    $component->call('selectStudent', $f['student']->id)->set('applicationId', $application->id)->call('grant')->assertHasErrors('schemeId');
    expect(DiscountAward::count())->toBe(1);
});

it('validates award figures, scheme ownership and sponsors at grant', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.award.grant');
    $scheme = fin07AdminScheme($f, ['code' => 'GEN']);
    $sponsored = fin07AdminScheme($f, ['code' => 'CORP', 'is_sponsor_funded' => true, 'category' => 'corporate']);
    $otherSchoolScheme = DiscountScheme::factory()->for(School::factory()->create())->create();
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(GrantAward::class, ['school' => $f['school']])->call('selectStudent', $f['student']->id)->set('schemeId', $scheme->id);

    $component->set('percent', '150')->call('grant')->assertHasErrors();
    $component->set('percent', '')->call('grant')->assertHasErrors('schemeId');
    $component->set('schemeId', $sponsored->id)->set('percent', '100')->call('grant')->assertHasErrors('schemeId');

    expect(fn () => $component->set('schemeId', $otherSchoolScheme->id)->set('percent', '10')->call('grant'))->toThrow(ModelNotFoundException::class);
    expect(DiscountAward::count())->toBe(0);
});

it('routes an award that needs approval to pending instead of activating it', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.award.grant');
    $approver = User::factory()->create();
    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'discount_award', name: 'Award Approval', isDefault: true, createdByUserId: $user->id,
        steps: [new ApprovalStepData(stepNumber: 1, name: 'Bursar sign-off', approverType: 'user', mode: 'parallel_any', approverUserId: $approver->id)],
    ));
    $scheme = fin07AdminScheme($f, ['code' => 'NEEDS', 'requires_approval' => true]);

    Livewire::actingAs($user)->test(GrantAward::class, ['school' => $f['school']])->call('selectStudent', $f['student']->id)->set('schemeId', $scheme->id)->set('percent', '25')->call('grant')->assertHasNoErrors();

    expect(DiscountAward::firstOrFail()->status)->toBe('pending_approval');
});

it('submits an application on a family’s behalf and refuses a non-application scheme or a duplicate', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.scholarship.review', 'finance.scholarship.apply');
    $scheme = fin07AdminScheme($f, ['scheme_type' => 'application_based', 'code' => 'MERIT']);

    $component = Livewire::actingAs($user)->test(Applications::class, ['school' => $f['school']])
        ->call('selectStudent', $f['student']->id)->set('schemeId', $scheme->id)->set('incomeBand', 'under_500')->set('meansScore', '72.5')->call('submit')->assertHasNoErrors();

    expect(ScholarshipApplication::firstOrFail())->status->toBe('submitted')->household_income_band->toBe('under_500');

    $component->call('selectStudent', $f['student']->id)->set('schemeId', $scheme->id)->call('submit')->assertHasErrors('schemeId');
    expect(ScholarshipApplication::count())->toBe(1);

    $manual = fin07AdminScheme($f, ['code' => 'MANUAL']);
    $component->call('selectStudent', $f['student']->id)->set('schemeId', $manual->id)->call('submit')->assertHasErrors('schemeId');
});

it('lists means data to a reviewer only, and lets a reviewer without apply see but not submit', function (): void {
    $f = fin07AdminFixture();
    $reviewer = fin07AdminUser($f, 'finance.scholarship.review');
    $scheme = fin07AdminScheme($f, ['scheme_type' => 'application_based']);
    ScholarshipApplication::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id, 'household_income_band' => 'BAND-MARKER']);

    Livewire::actingAs($reviewer)->test(Applications::class, ['school' => $f['school']])->assertSee('BAND-MARKER')->set('schemeId', $scheme->id)->call('submit')->assertForbidden();

    $applyOnly = fin07AdminUser($f, 'finance.scholarship.apply');
    Livewire::actingAs($applyOnly)->test(Applications::class, ['school' => $f['school']])->assertForbidden();
});

it('records a committee decision with its rationale, requires a reason to reject, and keeps a decision final (BR-FIN-07-006)', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.scholarship.decide');
    $scheme = fin07AdminScheme($f, ['scheme_type' => 'application_based']);
    $application = ScholarshipApplication::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id, 'status' => 'submitted']);

    $component = Livewire::actingAs($user)->test(Committee::class, ['school' => $f['school']])->call('begin', $application->id);

    $component->set('status', 'approved')->set('notes', '')->call('decide')->assertHasErrors('notes');
    $component->set('status', 'rejected')->set('notes', 'Does not meet means test.')->set('rejectionReason', '')->call('decide')->assertHasErrors('notes');
    expect($application->fresh()->status)->toBe('submitted');

    $component->set('status', 'approved')->set('notes', 'Strong case; means verified.')->call('decide')->assertHasNoErrors();
    expect($application->fresh())->status->toBe('approved')->committee_notes->toBe('Strong case; means verified.')->decided_by->toBe($user->id);

    expect(fn () => $component->call('begin', $application->id))->toThrow(ModelNotFoundException::class);
});

it('suspends — never revokes — an academic award below its threshold, and reinstates only with a reason (AC-FIN-07-005)', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.award.review');
    $scheme = fin07AdminScheme($f, ['code' => 'ACADEMIC', 'requires_academic_threshold' => true, 'minimum_average_percent' => '60.00']);
    $award = DiscountAward::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id, 'status' => 'active']);
    TermResult::factory()->create(['school_id' => $f['school']->id, 'student_id' => $f['student']->id, 'term_id' => $f['term']->id, 'average_percent' => '54.00']);

    $component = Livewire::actingAs($user)->test(ConditionReview::class, ['school' => $f['school']])->set('termId', $f['term']->id)->call('review', $award->id);

    expect($award->fresh())->status->toBe('suspended')->condition_met->toBeFalse();

    $component->call('beginReinstate', $award->id)->set('reason', '')->call('reinstate')->assertHasErrors('reason');
    expect($award->fresh()->status)->toBe('suspended');

    $component->set('reason', 'Mitigating circumstances accepted by the committee.')->call('reinstate');
    expect($award->fresh()->status)->toBe('active');
});

it('refuses a condition review of an award in another academic year’s term', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.award.review');
    $scheme = fin07AdminScheme($f, ['requires_academic_threshold' => true, 'minimum_average_percent' => '60.00']);
    $award = DiscountAward::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id]);
    $otherYearTerm = Term::factory()->for($f['school'])->for(AcademicYear::factory()->for($f['school'])->create(), 'academicYear')->create();

    Livewire::actingAs($user)->test(ConditionReview::class, ['school' => $f['school']])->set('termId', $otherYearTerm->id)->call('review', $award->id);

    expect($award->fresh()->condition_last_checked_at)->toBeNull();
});

it('revokes only with a reason and the revoke permission, and a revoked award cannot be revoked again (BR-FIN-07-012)', function (): void {
    $f = fin07AdminFixture();
    $viewer = fin07AdminUser($f, 'finance.award.view');
    $revoker = fin07AdminUser($f, 'finance.award.view', 'finance.award.revoke');
    $scheme = fin07AdminScheme($f);
    $award = DiscountAward::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id, 'status' => 'active']);

    Livewire::actingAs($viewer)->test(AwardList::class, ['school' => $f['school']])->assertSee('Tendai')->call('beginRevoke', $award->id)->assertForbidden();

    $component = Livewire::actingAs($revoker)->test(AwardList::class, ['school' => $f['school']])->call('beginRevoke', $award->id)->set('reason', '')->call('revoke')->assertHasErrors('reason');
    expect($award->fresh()->status)->toBe('active');

    $component->set('reason', 'Learner withdrew.')->call('revoke')->assertHasNoErrors();
    expect($award->fresh())->status->toBe('revoked')->revoked_reason->toBe('Learner withdrew.');

    expect(fn () => $component->call('beginRevoke', $award->id))->toThrow(ModelNotFoundException::class);
});

it('shows gross, discount and net separately per scheme (AC-FIN-07-006)', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.report.discounts');
    $scheme = fin07AdminScheme($f, ['code' => 'GENEROUS', 'name' => 'Generous Fund']);
    $award = DiscountAward::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id]);
    $line = LearnerFeeLine::factory()->create(['gross_minor' => 50000, 'discount_minor' => 5000, 'net_minor' => 45000, 'school_id' => $f['school']->id]);
    AwardUtilisation::factory()->create(['school_id' => $f['school']->id, 'award_id' => $award->id, 'term_id' => $f['term']->id, 'fee_line_id' => $line->id, 'discount_minor' => 5000]);

    Livewire::actingAs($user)->test(DiscountsReport::class, ['school' => $f['school']])->set('termId', $f['term']->id)
        ->assertSee('Generous Fund')->assertSee('500.00')->assertSee('50.00')->assertSee('450.00');
});

it('lists sponsor-funded awards with a link to the learner’s liabilities, not a discount figure', function (): void {
    $f = fin07AdminFixture();
    $user = fin07AdminUser($f, 'finance.award.grant');
    $guardian = Guardian::factory()->for($f['school'])->create(['first_name' => 'Acme', 'last_name' => 'Trust']);
    $scheme = fin07AdminScheme($f, ['code' => 'CORPSPONSOR', 'name' => 'Corporate Sponsor', 'is_sponsor_funded' => true]);
    DiscountAward::factory()->for($f['school'])->create(['scheme_id' => $scheme->id, 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id, 'sponsor_guardian_id' => $guardian->id]);

    Livewire::actingAs($user)->test(SponsorAwards::class, ['school' => $f['school']])->assertSee('Acme Trust')->assertSee('Corporate Sponsor')->assertSee('Liabilities');
});
