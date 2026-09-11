<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Actions\Schools\ToggleSchoolModuleAction;
use Modules\Core\Domain\DataObjects\Schools\ToggleModuleData;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Finance\Models\Journal;
use Modules\Saas\Domain\Actions\ApplyOnboardingTemplateAction;
use Modules\Saas\Domain\Actions\CheckSupportTicketSlaAction;
use Modules\Saas\Domain\Actions\CompleteOnboardingStepAction;
use Modules\Saas\Domain\Actions\CompleteTourAction;
use Modules\Saas\Domain\Actions\DistributeReleaseNotesAction;
use Modules\Saas\Domain\Actions\GetMySupportTicketsAction;
use Modules\Saas\Domain\Actions\ListDormantEntitledModulesAction;
use Modules\Saas\Domain\Actions\ListStalledOnboardingChecklistsAction;
use Modules\Saas\Domain\Actions\RaiseChurnRiskFlagAction;
use Modules\Saas\Domain\Actions\RaiseSupportTicketAction;
use Modules\Saas\Domain\Actions\RecordModuleAdoptionAction;
use Modules\Saas\Domain\Actions\SkipTourAction;
use Modules\Saas\Domain\Actions\StartOnboardingChecklistAction;
use Modules\Saas\Domain\DataObjects\ApplyOnboardingTemplateData;
use Modules\Saas\Domain\DataObjects\DistributeReleaseNotesData;
use Modules\Saas\Domain\DataObjects\RaiseSupportTicketData;
use Modules\Saas\Domain\DataObjects\StartOnboardingChecklistData;
use Modules\Saas\Domain\Exceptions\OnboardingStepNotFoundException;
use Modules\Saas\Models\ModuleAdoptionScore;
use Modules\Saas\Models\OnboardingChecklist;
use Modules\Saas\Models\OnboardingTemplate;
use Modules\Saas\Models\ProductTour;
use Modules\Saas\Models\SupportTicket;
use Modules\Welfare\Models\SickBayAdmission;

it('starts an onboarding checklist and completes steps through to go_live (BR-SAA-03-001)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();

    $checklist = app(StartOnboardingChecklistAction::class)->execute(new StartOnboardingChecklistData(
        tenantId: $tenant->id,
        schoolId: $school->id,
        steps: [['key' => 'data_import', 'label' => 'Import data'], ['key' => 'training', 'label' => 'Staff training']],
    ));

    expect($checklist->status)->toBe('in_progress');

    app(CompleteOnboardingStepAction::class)->execute($checklist->id, 'data_import');
    expect($checklist->fresh()->status)->toBe('in_progress');

    $completed = app(CompleteOnboardingStepAction::class)->execute($checklist->id, 'training');
    expect($completed->status)->toBe('go_live')
        ->and($completed->isFullyComplete())->toBeTrue();

    expect(fn () => app(CompleteOnboardingStepAction::class)->execute($checklist->id, 'unknown_step'))
        ->toThrow(OnboardingStepNotFoundException::class);
});

it('alerts the assigned success manager once a checklist has stalled (AC-SAA-03-003)', function (): void {
    $manager = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();

    // 30 days idle comfortably clears the registered default threshold
    // (`saas.onboarding_stall_threshold_days` = 14) — a system-scoped
    // setting resolves only to its registered default in this pass
    // (ScopeChain never contributes a System-level id to override it),
    // so the test works against that default rather than trying to
    // override it.
    OnboardingChecklist::factory()->create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'started_at' => Carbon::now()->subDays(30),
        'status' => 'in_progress',
        'assigned_success_manager' => $manager->id,
    ]);

    $stalled = app(ListStalledOnboardingChecklistsAction::class)->execute();

    expect($stalled)->toHaveCount(1);
});

it('applies an onboarding template by cloning its configuration profile and increments used_count (BR-SAA-03-002)', function (): void {
    $tenant = Tenant::factory()->create();
    $targetSchool = School::factory()->for($tenant)->create();
    $admin = User::factory()->create();

    $profile = ConfigurationProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'payload' => ['settings' => [['key' => 'integration.default_rate_limit_per_minute', 'value' => '30']], 'custom_fields' => []],
    ]);

    $template = OnboardingTemplate::factory()->create(['configuration_profile_id' => $profile->id, 'used_count' => 2]);

    $result = app(ApplyOnboardingTemplateAction::class)->execute(new ApplyOnboardingTemplateData(
        templateId: $template->id,
        targetSchoolId: $targetSchool->id,
        actingUserId: $admin->id,
    ));

    expect($result->settingsImported)->toBe(1)
        ->and($template->fresh()->used_count)->toBe(3);
});

it('raises a support ticket that never touches the COM-08 complaint queue (BR-SAA-03-003/AC-SAA-03-001)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();
    $user = User::factory()->create();

    $ticket = app(RaiseSupportTicketAction::class)->execute(new RaiseSupportTicketData(
        tenantId: $tenant->id,
        schoolId: $school->id,
        raisedByUserId: $user->id,
        subject: 'Cannot generate report cards',
        description: 'Getting a blank PDF when exporting.',
        category: 'bug',
        priority: 'high',
    ));

    expect($ticket->status)->toBe('open')
        ->and(round($ticket->sla_due_at->diffInHours(Carbon::now(), absolute: true)))->toBe(8.0)
        ->and(Complaint::count())->toBe(0);
});

it('flags a breached support ticket SLA and notifies the assigned vendor staff member (BR-SAA-03-004)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();
    $vendorStaff = User::factory()->create();

    $ticket = SupportTicket::factory()->create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'assigned_vendor_staff_id' => $vendorStaff->id,
        'sla_due_at' => Carbon::now()->subHour(),
        'status' => 'in_progress',
    ]);

    $result = app(CheckSupportTicketSlaAction::class)->execute($ticket->id);

    expect($result['breached'])->toBeTrue();
});

it('records both completion and skip for a product tour, and a skipped tour stays completable (BR-SAA-03-005)', function (): void {
    $user = User::factory()->create();
    $tour = ProductTour::factory()->create();

    $skipped = app(SkipTourAction::class)->execute($user->id, $tour->key);
    expect($skipped->skipped_at)->not->toBeNull()
        ->and($skipped->completed_at)->toBeNull();

    $completed = app(CompleteTourAction::class)->execute($user->id, $tour->key);
    expect($completed->completed_at)->not->toBeNull();
});

it('derives module adoption from real recorded activity, never a self-report (BR-SAA-03-006/AC-SAA-03-002)', function (): void {
    $school = School::factory()->create();

    Journal::factory()->count(5)->create(['school_id' => $school->id, 'posted_at' => Carbon::now()]);

    $scores = app(RecordModuleAdoptionAction::class)->execute($school->id);

    $fin = collect($scores)->firstWhere('module_code', 'FIN');
    $brd = collect($scores)->firstWhere('module_code', 'BRD');

    expect($fin->activity_count)->toBe(5)
        ->and($fin->is_actively_used)->toBeTrue()
        ->and($brd->activity_count)->toBe(0)
        ->and($brd->is_actively_used)->toBeFalse();
});

it('records a zero sick bay count for BRD-06 exactly like the specs own worked example', function (): void {
    $school = School::factory()->create();

    SickBayAdmission::factory()->count(2)->create(['school_id' => $school->id, 'admitted_at' => Carbon::now()]);

    $scores = app(RecordModuleAdoptionAction::class)->execute($school->id);
    $brd = collect($scores)->firstWhere('module_code', 'BRD');

    expect($brd->activity_count)->toBe(2)->and($brd->is_actively_used)->toBeTrue();
});

it('surfaces a module the school is entitled to but has not touched for the sustained window (BR-SAA-03-007)', function (): void {
    $school = School::factory()->create();

    // Matches the registered default (`saas.dormant_module_sustained_months`
    // = 3) — see the onboarding-stall test above for why this pass
    // works against the default rather than overriding it.
    app(ToggleSchoolModuleAction::class)->execute(new ToggleModuleData(schoolId: $school->id, moduleCode: 'FIN', enable: true));

    foreach ([0, 1, 2] as $monthsAgo) {
        ModuleAdoptionScore::factory()->create([
            'school_id' => $school->id,
            'module_code' => 'FIN',
            'period_month' => Carbon::today()->subMonthsNoOverflow($monthsAgo)->format('Y-m'),
            'is_actively_used' => false,
        ]);
    }

    $dormant = app(ListDormantEntitledModulesAction::class)->execute($school->id);

    expect($dormant)->toContain('FIN');
});

it('decomposes a churn risk flag into plain-language, sourced factors and never duplicates an open one (BR-SAA-03-008/AC-SAA-03-005)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->forceFill(['last_login_at' => Carbon::now()->subDays(120)])->save();

    $flag = app(RaiseChurnRiskFlagAction::class)->execute($tenant->id);

    expect($flag)->not->toBeNull()
        ->and($flag->contributing_factors)->not->toBeEmpty();

    foreach ($flag->contributing_factors as $factor) {
        expect($factor)->toHaveKeys(['indicator', 'plain_language', 'weight', 'contribution', 'source']);
    }

    $again = app(RaiseChurnRiskFlagAction::class)->execute($tenant->id);
    expect($again)->toBeNull();
});

it('scopes release note distribution to schools with the module actually enabled (BR-SAA-03-009/AC-SAA-03-004)', function (): void {
    $schoolWithModule = School::factory()->create();
    $schoolWithoutModule = School::factory()->create();
    $adminWith = User::factory()->create();
    $adminWithout = User::factory()->create();

    app(ToggleSchoolModuleAction::class)->execute(new ToggleModuleData(schoolId: $schoolWithModule->id, moduleCode: 'BRD', enable: true));

    $notified = app(DistributeReleaseNotesAction::class)->execute(new DistributeReleaseNotesData(
        moduleCode: 'BRD',
        notificationKey: 'saas.release_notes',
        context: ['module_name' => 'Boarding', 'version' => '2.1', 'summary' => 'New roll call flow.'],
        candidateRecipients: [
            ['school_id' => $schoolWithModule->id, 'admin_user_id' => $adminWith->id, 'email' => $adminWith->email],
            ['school_id' => $schoolWithoutModule->id, 'admin_user_id' => $adminWithout->id, 'email' => $adminWithout->email],
        ],
    ));

    expect($notified)->toBe(1);
});

it('never resolves another users support tickets', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $ticketA = SupportTicket::factory()->create(['raised_by_user_id' => $userA->id]);
    SupportTicket::factory()->create(['raised_by_user_id' => $userB->id]);

    $resolved = app(GetMySupportTicketsAction::class)->execute($userA->id);

    expect($resolved)->toHaveCount(1)
        ->and($resolved->first()->id)->toBe($ticketA->id);
});
