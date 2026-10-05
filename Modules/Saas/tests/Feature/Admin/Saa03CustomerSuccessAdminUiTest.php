<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\AssignSupportTicketAction;
use Modules\Saas\Domain\Actions\RaiseSupportTicketAction;
use Modules\Saas\Domain\Actions\ReviewChurnRiskFlagAction;
use Modules\Saas\Domain\Actions\StartOnboardingChecklistAction;
use Modules\Saas\Domain\DataObjects\RaiseSupportTicketData;
use Modules\Saas\Domain\DataObjects\StartOnboardingChecklistData;
use Modules\Saas\Livewire\Tenant\Support\Raise;
use Modules\Saas\Livewire\Vendor\Adoption\Index as AdoptionIndex;
use Modules\Saas\Livewire\Vendor\ChurnRisk\Queue as ChurnQueue;
use Modules\Saas\Livewire\Vendor\Onboarding\Index as OnboardingIndex;
use Modules\Saas\Livewire\Vendor\Onboarding\Templates;
use Modules\Saas\Livewire\Vendor\Support\Queue as SupportQueue;
use Modules\Saas\Models\ChurnRiskFlag;
use Modules\Saas\Models\KnowledgeBaseArticle;
use Modules\Saas\Models\ModuleAdoptionScore;
use Modules\Saas\Models\OnboardingChecklist;
use Modules\Saas\Models\OnboardingTemplate;
use Modules\Saas\Models\SupportTicket;

/**
 * Book J SAA-03 admin-UI pass. Own, distinctly-named helpers.
 */
function saa03AdminVendor(): User
{
    return User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => now()]);
}

/**
 * @return array{tenant: Tenant, school: School, user: User}
 */
function saa03AdminSchoolUser(bool $withRaisePermission = true): array
{
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id]);
    SchoolContext::set($school);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->schools()->attach($school, ['status' => 'active']);

    if ($withRaisePermission) {
        $permission = Permission::firstOrCreate(['name' => 'support.ticket.raise'], ['guard_name' => 'web', 'module_code' => 'SUPPORT', 'resource' => 'ticket', 'action' => 'raise']);
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $school->id, grants: [new PermissionGrantData($permission->id, PermissionScope::School)]));
    }

    return compact('tenant', 'school', 'user');
}

it('refuses every SAA-03 vendor component to a school user (AC-SAA-02-001)', function (string $component): void {
    ['user' => $user] = saa03AdminSchoolUser();

    Livewire::actingAs($user)->test($component)->assertForbidden();
})->with([
    'onboarding' => OnboardingIndex::class,
    'templates' => Templates::class,
    'queue' => SupportQueue::class,
    'adoption' => AdoptionIndex::class,
    'churn' => ChurnQueue::class,
]);

it('raises a ticket to the vendor with tenant, school and author taken from the server, never the complaint queue (AC-SAA-03-001)', function (): void {
    ['school' => $school, 'user' => $user, 'tenant' => $tenant] = saa03AdminSchoolUser();

    Livewire::actingAs($user)->test(Raise::class, ['school' => $school])
        ->set('subject', 'Cannot print report cards')->set('description', 'Blank PDF.')->set('category', 'bug')->set('priority', 'high')
        ->call('raise')->assertHasNoErrors()->assertSee('Cannot print report cards');

    $ticket = SupportTicket::withoutGlobalScopes()->firstOrFail();

    expect($ticket)->tenant_id->toBe($tenant->id)->school_id->toBe($school->id)->raised_by_user_id->toBe($user->id)->status->toBe('open')
        ->and(Complaint::withoutGlobalScopes()->count())->toBe(0);

    Livewire::actingAs(saa03AdminVendor())->test(SupportQueue::class)->assertSee('Cannot print report cards');
});

it('shows a user only their own tickets, and refuses another tenant’s user and a user without the permission', function (): void {
    ['school' => $school, 'user' => $user, 'tenant' => $tenant] = saa03AdminSchoolUser();
    $colleague = User::factory()->create(['tenant_id' => $tenant->id]);
    SupportTicket::factory()->create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'raised_by_user_id' => $colleague->id, 'subject' => 'Colleague private ticket']);
    SupportTicket::factory()->create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'raised_by_user_id' => $user->id, 'subject' => 'My own ticket']);

    Livewire::actingAs($user)->test(Raise::class, ['school' => $school])->assertSee('My own ticket')->assertDontSee('Colleague private ticket');

    $stranger = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $stranger->schools()->attach($school, ['status' => 'active']);
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $stranger->id, schoolId: $school->id, grants: [new PermissionGrantData(Permission::where('name', 'support.ticket.raise')->firstOrFail()->id, PermissionScope::School)]));

    Livewire::actingAs($stranger)->test(Raise::class, ['school' => $school])->assertForbidden();
    Livewire::actingAs($colleague)->test(Raise::class, ['school' => $school])->assertForbidden();
});

it('refuses a ticket whose school or author is not the tenant’s, or whose priority is unknown', function (): void {
    ['school' => $school, 'user' => $user, 'tenant' => $tenant] = saa03AdminSchoolUser();
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $make = fn (int $tenantId, ?int $schoolId, int $userId, string $priority = 'normal') => new RaiseSupportTicketData($tenantId, $schoolId, $userId, 'Subject', 'Body', 'bug', $priority);

    expect(fn () => app(RaiseSupportTicketAction::class)->execute($make($otherTenant->id, $school->id, $otherUser->id)))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RaiseSupportTicketAction::class)->execute($make($tenant->id, $school->id, $otherUser->id)))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RaiseSupportTicketAction::class)->execute($make($tenant->id, $school->id, $user->id, 'whenever')))->toThrow(InvalidArgumentException::class);
});

it('sorts the vendor queue by SLA, flags breaches, assigns only to vendor staff and keeps a closed ticket closed', function (): void {
    $vendor = saa03AdminVendor();
    ['school' => $school, 'user' => $user, 'tenant' => $tenant] = saa03AdminSchoolUser();
    $soon = SupportTicket::factory()->create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'raised_by_user_id' => $user->id, 'subject' => 'Overdue one', 'sla_due_at' => now()->subHour()]);
    $later = SupportTicket::factory()->create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'raised_by_user_id' => $user->id, 'subject' => 'Later one', 'sla_due_at' => now()->addDay()]);

    $component = Livewire::actingAs($vendor)->test(SupportQueue::class)->assertSeeInOrder(['Overdue one', 'Later one'])->assertSee('Breached');

    $component->call('assign', $soon->id, $vendor->id);
    expect($soon->fresh())->assigned_vendor_staff_id->toBe($vendor->id)->status->toBe('in_progress');

    $component->call('assign', $later->id, $user->id);
    expect($later->fresh()->assigned_vendor_staff_id)->toBeNull();

    $component->call('changeStatus', $soon->id, 'resolved')->call('changeStatus', $soon->id, 'closed');
    expect($soon->fresh()->status)->toBe('closed');

    $component->call('changeStatus', $soon->id, 'open');
    expect($soon->fresh()->status)->toBe('closed');
    expect(fn () => app(AssignSupportTicketAction::class)->execute($soon->id, $vendor->id))->toThrow(InvalidArgumentException::class);

    $component->call('changeStatus', $later->id, 'closed');
    expect($later->fresh()->status)->toBe('open');
});

it('starts onboarding only for a school of the tenant, once, and flips to go-live when every step is done (AC-SAA-03-003)', function (): void {
    $vendor = saa03AdminVendor();
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id]);
    $foreign = School::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $component = Livewire::actingAs($vendor)->test(OnboardingIndex::class)->set('tenantId', $tenant->id)->set('schoolId', $foreign->id)->call('start')->assertHasErrors('schoolId');
    expect(OnboardingChecklist::withoutGlobalScopes()->count())->toBe(0);

    $component->set('schoolId', $school->id)->call('start')->assertHasNoErrors();
    $checklist = OnboardingChecklist::withoutGlobalScopes()->firstOrFail();

    expect($checklist->steps)->toHaveCount(6)->and($checklist->status)->toBe('in_progress');

    $component->set('schoolId', $school->id)->call('start')->assertHasErrors('schoolId');

    foreach ($checklist->steps as $step) {
        $component->call('completeStep', $checklist->id, $step['key']);
    }

    expect($checklist->fresh()->status)->toBe('go_live');

    expect(fn () => app(StartOnboardingChecklistAction::class)->execute(new StartOnboardingChecklistData($tenant->id, $school->id, [['key' => 'a', 'label' => 'A'], ['key' => 'a', 'label' => 'B']])))->toThrow(InvalidArgumentException::class);
});

it('rejects a non-vendor success manager and shows a stalled checklist', function (): void {
    $vendor = saa03AdminVendor();
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id]);
    $schoolUser = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($vendor)->test(OnboardingIndex::class)->set('tenantId', $tenant->id)->set('schoolId', $school->id)->set('managerId', $schoolUser->id)->call('start')->assertHasErrors('schoolId');

    $stalledSchool = School::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Stalled Academy']);
    OnboardingChecklist::factory()->create(['tenant_id' => $tenant->id, 'school_id' => $stalledSchool->id, 'started_at' => now()->subDays(40)]);

    Livewire::actingAs($vendor)->test(OnboardingIndex::class)->assertSee('Stalled Academy')->assertSee('Stalled');
});

it('adds a profile to the library once and applies it only to a school of the chosen tenant, additively', function (): void {
    $vendor = saa03AdminVendor();
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id]);
    $foreign = School::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $profile = ConfigurationProfile::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Boarding secondary', 'payload' => ['settings' => [['key' => 'integration.default_rate_limit_per_minute', 'value' => '30']], 'custom_fields' => []]]);

    $component = Livewire::actingAs($vendor)->test(Templates::class)->set('profileId', $profile->id)->set('templateName', 'Boarding secondary starter')->set('suitedFor', 'boarding_secondary')->call('create')->assertHasNoErrors();
    $component->set('profileId', $profile->id)->set('templateName', 'Duplicate')->call('create')->assertHasErrors('profileId');

    $template = OnboardingTemplate::firstOrFail();

    $component->call('beginApply', $template->id)->set('tenantId', $tenant->id)->set('schoolId', $foreign->id);
    expect(fn () => $component->call('apply'))->toThrow(ModelNotFoundException::class);
    expect($template->fresh()->used_count)->toBe(0);

    $component->set('schoolId', $school->id)->call('apply')->assertSee('1 settings imported');
    expect($template->fresh()->used_count)->toBe(1);
});

it('shows adoption from recorded activity and lists dormant modules (AC-SAA-03-002)', function (): void {
    $vendor = saa03AdminVendor();
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($vendor)->test(AdoptionIndex::class)->set('tenantId', $tenant->id)->set('schoolId', $school->id)->call('recompute');

    expect(ModuleAdoptionScore::withoutGlobalScopes()->where('school_id', $school->id)->count())->toBeGreaterThan(0)
        ->and(ModuleAdoptionScore::withoutGlobalScopes()->where('school_id', $school->id)->where('is_actively_used', true)->count())->toBe(0);

    Livewire::actingAs($vendor)->test(AdoptionIndex::class)->set('tenantId', $tenant->id)->set('schoolId', $school->id)->call('recompute')->assertSee('No activity');
});

it('refuses to recompute adoption for a school outside the chosen tenant or for a malformed month', function (): void {
    $vendor = saa03AdminVendor();
    $tenant = Tenant::factory()->create();
    $foreign = School::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    Livewire::actingAs($vendor)->test(AdoptionIndex::class)->set('tenantId', $tenant->id)->set('schoolId', $foreign->id)->call('recompute');
    Livewire::actingAs($vendor)->test(AdoptionIndex::class)->set('tenantId', $foreign->tenant_id)->set('schoolId', $foreign->id)->set('periodMonth', '2026-13')->call('recompute');

    expect(ModuleAdoptionScore::withoutGlobalScopes()->count())->toBe(0);
});

it('shows a churn flag’s factors in plain language with sources, and keeps a closed flag closed (AC-SAA-03-005)', function (): void {
    $vendor = saa03AdminVendor();
    $tenant = Tenant::factory()->create(['name' => 'Risky Group']);
    $flag = ChurnRiskFlag::factory()->create(['tenant_id' => $tenant->id, 'contributing_factors' => [['indicator' => 'login_recency', 'plain_language' => 'No recorded login for 45 days across this tenant’s users', 'weight' => 40, 'contribution' => 30, 'source' => 'users.last_login_at']]]);

    $component = Livewire::actingAs($vendor)->test(ChurnQueue::class)->assertSee('Risky Group')->assertSee('No recorded login for 45 days')->assertSee('users.last_login_at');

    $component->call('move', $flag->id, 'intervention_logged')->call('move', $flag->id, 'resolved');
    expect($flag->fresh()->status)->toBe('resolved');

    $component->call('move', $flag->id, 'open');
    expect($flag->fresh()->status)->toBe('resolved');
});

it('assigns a churn flag to vendor staff only', function (): void {
    $vendor = saa03AdminVendor();
    $schoolUser = User::factory()->create();
    $flag = ChurnRiskFlag::factory()->create();

    $component = Livewire::actingAs($vendor)->test(ChurnQueue::class)->call('assign', $flag->id, $schoolUser->id);
    expect($flag->fresh()->assigned_to)->toBeNull();

    $component->call('assign', $flag->id, $vendor->id);
    expect($flag->fresh()->assigned_to)->toBe($vendor->id);

    expect(fn () => app(ReviewChurnRiskFlagAction::class)->execute($flag->id, 'bogus'))->toThrow(InvalidArgumentException::class);
});

it('serves the help centre publicly, searchable, with article HTML escaped', function (): void {
    KnowledgeBaseArticle::factory()->create(['slug' => 'print-report-cards', 'title' => 'Printing report cards', 'content' => "Open **Reports**.\n\n<script>alert('x')</script>"]);
    KnowledgeBaseArticle::factory()->create(['slug' => 'fees', 'title' => 'Setting up fees', 'content' => 'Fee structures.']);

    $this->get('/help')->assertOk()->assertSee('Printing report cards')->assertSee('Setting up fees');
    $this->get('/help?search=report')->assertOk()->assertSee('Printing report cards')->assertDontSee('Setting up fees');

    $this->get('/help/print-report-cards')->assertOk()->assertSee('<strong>Reports</strong>', false)->assertDontSee("<script>alert('x')</script>", false);
    $this->get('/help/does-not-exist')->assertNotFound();
});
