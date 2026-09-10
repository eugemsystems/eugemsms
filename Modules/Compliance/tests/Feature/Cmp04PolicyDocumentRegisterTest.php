<?php

use App\Models\User;
use Modules\Compliance\Domain\Actions\AccessGovernanceMinuteAction;
use Modules\Compliance\Domain\Actions\AcknowledgePolicyAction;
use Modules\Compliance\Domain\Actions\CheckDocumentExpiryAction;
use Modules\Compliance\Domain\Actions\CheckPolicyReviewDueAction;
use Modules\Compliance\Domain\Actions\CreateContractAction;
use Modules\Compliance\Domain\Actions\CreateGovernanceMinuteAction;
use Modules\Compliance\Domain\Actions\CreatePolicyAction;
use Modules\Compliance\Domain\Actions\CreateStatutoryDocumentAction;
use Modules\Compliance\Domain\Actions\GenerateConsolidatedIncidentRegisterAction;
use Modules\Compliance\Domain\Actions\ReportPolicyAcknowledgementStatusAction;
use Modules\Compliance\Domain\DataObjects\AcknowledgePolicyData;
use Modules\Compliance\Domain\DataObjects\CreateContractData;
use Modules\Compliance\Domain\DataObjects\CreateGovernanceMinuteData;
use Modules\Compliance\Domain\DataObjects\CreatePolicyData;
use Modules\Compliance\Domain\DataObjects\CreateStatutoryDocumentData;
use Modules\Compliance\Domain\Exceptions\MinuteAccessDeniedException;
use Modules\Compliance\Models\DataBreach;
use Modules\Compliance\Models\GovernanceMinute;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Security\Models\OccurrenceBookEntry;
use Modules\Transport\Models\VehicleIncident;
use Modules\Welfare\Models\BehaviourRecord;
use Modules\Welfare\Models\HealthIncident;

/**
 * @return array<string, mixed>
 */
function cmp04Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    return compact('school', 'user');
}

it('supersedes an earlier policy version while acknowledgements of that version remain on record (AC-CMP-04-001)', function (): void {
    $f = cmp04Fixture();
    $staff = Staff::factory()->for($f['school'])->create();

    $v3 = app(CreatePolicyAction::class)->execute(new CreatePolicyData(
        schoolId: $f['school']->id, code: 'CHILD-PROTECTION', title: 'Child Protection Policy',
        category: 'safeguarding', version: 'v3', effectiveFrom: now()->subYear()->toDateString(),
        requiresAcknowledgement: true, acknowledgementAudiences: ['staff'],
    ));
    app(AcknowledgePolicyAction::class)->execute(new AcknowledgePolicyData(
        policyId: $v3->id, acknowledgedByType: 'staff', acknowledgedById: $staff->id, method: 'portal',
    ));

    $v4 = app(CreatePolicyAction::class)->execute(new CreatePolicyData(
        schoolId: $f['school']->id, code: 'CHILD-PROTECTION', title: 'Child Protection Policy',
        category: 'safeguarding', version: 'v4', effectiveFrom: now()->toDateString(),
        requiresAcknowledgement: true, acknowledgementAudiences: ['staff'], supersedesPolicyId: $v3->id,
    ));

    expect($v3->fresh()->status)->toBe('superseded')
        ->and($v4->status)->toBe('active');

    $report = app(ReportPolicyAcknowledgementStatusAction::class)->execute($v4->id);
    expect($report['staff']['outstanding'])->toContain($staff->id)
        ->and($report['staff']['acknowledged'])->not->toContain($staff->id);

    $oldAck = $v3->fresh()->acknowledgements()->first();
    expect($oldAck->policy_version)->toBe('v3');
});

it('reports policies past their review date (BR-CMP-04-004)', function (): void {
    $f = cmp04Fixture();
    $policy = app(CreatePolicyAction::class)->execute(new CreatePolicyData(
        schoolId: $f['school']->id, code: 'IT-ACCEPTABLE-USE', title: 'IT Acceptable Use Policy',
        category: 'operations', version: 'v1', effectiveFrom: now()->subYears(2)->toDateString(),
        reviewDueOn: now()->subDay()->toDateString(),
    ));

    $overdue = app(CheckPolicyReviewDueAction::class)->execute($f['school']->id);

    expect($overdue->pluck('id')->all())->toContain($policy->id);
});

it('transitions a statutory document to expiring and then expired, raising a critical alert for a fire certificate (AC-CMP-04-002, BR-CMP-04-006)', function (): void {
    $f = cmp04Fixture();
    $critical = app(CreateStatutoryDocumentAction::class)->execute(new CreateStatutoryDocumentData(
        schoolId: $f['school']->id, documentType: 'fire_certificate', issuingAuthority: 'Fire Brigade',
        expiresOn: now()->addDays(10)->toDateString(), renewalLeadDays: 14,
    ));
    $nonCritical = app(CreateStatutoryDocumentAction::class)->execute(new CreateStatutoryDocumentData(
        schoolId: $f['school']->id, documentType: 'water_quality', issuingAuthority: 'City Council',
        expiresOn: now()->subDay()->toDateString(), renewalLeadDays: 14,
    ));

    $result = app(CheckDocumentExpiryAction::class)->execute($f['school']->id);
    $criticalRow = collect($result['statutory_documents'])->firstWhere('id', $critical->id);
    $nonCriticalRow = collect($result['statutory_documents'])->firstWhere('id', $nonCritical->id);

    expect($criticalRow->status)->toBe('expiring')
        ->and($nonCriticalRow->status)->toBe('expired')
        ->and($nonCriticalRow->isCritical())->toBeFalse();
});

it('applies the same expiry mechanism to contracts (BR-CMP-04-005)', function (): void {
    $f = cmp04Fixture();
    $contract = app(CreateContractAction::class)->execute(new CreateContractData(
        schoolId: $f['school']->id, counterpartyName: 'Example Catering', contractType: 'service_provider',
        startsOn: now()->subYear()->toDateString(), expiresOn: now()->addDays(5)->toDateString(), renewalLeadDays: 30,
    ));

    $result = app(CheckDocumentExpiryAction::class)->execute($f['school']->id);
    $row = collect($result['contracts'])->firstWhere('id', $contract->id);

    expect($row->status)->toBe('expiring');
});

it('refuses access to a confidential minute without a named role, and logs a successful access (BR-CMP-04-007)', function (): void {
    $f = cmp04Fixture();
    $minute = app(CreateGovernanceMinuteAction::class)->execute(new CreateGovernanceMinuteData(
        schoolId: $f['school']->id, body: 'board', meetingDate: now()->toDateString(),
        attendees: ['Chairperson', 'Head'], confidentiality: 'confidential', accessRoleIds: [7],
    ));

    expect(fn () => app(AccessGovernanceMinuteAction::class)->execute($minute->id, $f['user']->id, [1, 2]))
        ->toThrow(MinuteAccessDeniedException::class);

    $accessed = app(AccessGovernanceMinuteAction::class)->execute($minute->id, $f['user']->id, [7]);
    expect($accessed->id)->toBe($minute->id);

    $logged = ActivityLogEntry::where('subject_type', GovernanceMinute::class)
        ->where('subject_id', $minute->id)->first();
    expect($logged)->not->toBeNull()
        ->and($logged->causer_id)->toBe($f['user']->id);
});

it('consolidates health, discipline, security, transport and data-protection incidents, excluding safeguarding (AC-CMP-04-003)', function (): void {
    $f = cmp04Fixture();
    HealthIncident::factory()->create(['school_id' => $f['school']->id, 'occurred_at' => now()->subDays(2)]);
    BehaviourRecord::factory()->create(['school_id' => $f['school']->id, 'occurred_at' => now()->subDays(3)]);
    OccurrenceBookEntry::factory()->create(['school_id' => $f['school']->id, 'occurred_at' => now()->subDays(1)]);
    VehicleIncident::factory()->create(['school_id' => $f['school']->id, 'occurred_at' => now()->subDays(4)]);
    DataBreach::factory()->create(['school_id' => $f['school']->id, 'detected_at' => now()->subDays(5)]);

    $register = app(GenerateConsolidatedIncidentRegisterAction::class)->execute(
        $f['school']->id, now()->subWeek()->toDateString(), now()->toDateString(),
    );

    $sources = $register->pluck('source')->unique()->sort()->values()->all();

    expect($sources)->toBe(['data_protection', 'discipline', 'health', 'security', 'transport']);
});
