<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\Intelligence\Domain\Actions\BuildExecutiveDashboardAction;
use Modules\Intelligence\Domain\Actions\GenerateBoardPackAction;
use Modules\Intelligence\Domain\Actions\GenerateExecutiveDigestAction;
use Modules\Intelligence\Domain\Actions\GetEnrolmentComparativeAction;
use Modules\Intelligence\Domain\Actions\GetKpiValueAction;
use Modules\Intelligence\Domain\Actions\SetKpiTargetAction;
use Modules\Intelligence\Models\WarehouseSnapshot;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear}
 */
function int02Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create(['is_current' => true]);

    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: 'intelligence.executive_digest', channel: 'email',
        body: 'All green: {{ all_green }}, exceptions: {{ exception_count }}.',
    ));

    return compact('school', 'year');
}

it('renders a KPI red once it crosses below its warning threshold, not merely a bare number (AC-INT-02-001)', function (): void {
    $f = int02Fixture();

    Invoice::factory()->for($f['school'])->create([
        'net_minor' => 10000, 'paid_minor' => 8700, 'balance_minor' => 1300,
    ]);

    $result = app(GetKpiValueAction::class)->execute('collection_rate', $f['school']->id, $f['year']->id);

    expect($result->currentValue)->toBe(87.0)
        ->and($result->targetValue)->toBe(92.0)
        ->and($result->warningThresholdPercent)->toBe(90.0)
        ->and($result->status)->toBe('red');
});

it('renders a KPI green once a school-specific target override is met, overriding the system default (BR-INT-02-002)', function (): void {
    $f = int02Fixture();

    Invoice::factory()->for($f['school'])->create(['net_minor' => 10000, 'paid_minor' => 6000, 'balance_minor' => 4000]);

    app(SetKpiTargetAction::class)->execute($f['school']->id, 'collection_rate', $f['year']->id, targetValue: 50.0, warningThresholdPercent: 40.0);

    $result = app(GetKpiValueAction::class)->execute('collection_rate', $f['school']->id, $f['year']->id);

    expect($result->currentValue)->toBe(60.0)
        ->and($result->targetValue)->toBe(50.0)
        ->and($result->status)->toBe('green');
});

it('sends a short, confirmatory digest with no exceptions when every KPI is on target, not every value re-listed (AC-INT-02-002)', function (): void {
    $f = int02Fixture();

    Invoice::factory()->for($f['school'])->create(['net_minor' => 10000, 'paid_minor' => 10000, 'balance_minor' => 0]);
    $recipient = User::factory()->create(['email' => 'head@example.test']);

    $digest = app(GenerateExecutiveDigestAction::class)->execute($f['school']->id, $f['year']->id, $recipient);

    expect($digest->content_summary['all_green'])->toBeTrue()
        ->and($digest->content_summary['exceptions'])->toBe([])
        ->and($digest->sent_at)->not->toBeNull();

    $notification = Notification::where('school_id', $f['school']->id)->where('notification_key', 'intelligence.executive_digest')->firstOrFail();
    expect($notification->context['all_green'])->toBeTrue()
        ->and($notification->context['exception_count'])->toBe(0);
});

it('lists only the KPIs in exception in the digest once one falls out of target', function (): void {
    $f = int02Fixture();

    Invoice::factory()->for($f['school'])->create(['net_minor' => 10000, 'paid_minor' => 5000, 'balance_minor' => 5000]);
    $recipient = User::factory()->create(['email' => 'head@example.test']);

    $digest = app(GenerateExecutiveDigestAction::class)->execute($f['school']->id, $f['year']->id, $recipient);

    expect($digest->content_summary['all_green'])->toBeFalse()
        ->and(collect($digest->content_summary['exceptions'])->pluck('key'))->toContain('collection_rate');
});

it('assembles a board pack with FIN-12\'s own financial section unmodified alongside enrolment, staffing, and boarding (AC-INT-02-003)', function (): void {
    $f = int02Fixture();
    $term = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create();
    Student::factory()->for($f['school'])->count(3)->create(['status' => 'active']);
    $user = User::factory()->create();

    $pack = app(GenerateBoardPackAction::class)->execute(
        $f['school']->id, $term->id, ['enrolment', 'financial', 'staffing', 'boarding'], $user->id,
    );

    expect($pack->sections_included)->toBe(['enrolment', 'financial', 'staffing', 'boarding'])
        ->and($pack->document_id)->not->toBeNull();

    $file = File::findOrFail($pack->document_id);
    $contents = json_decode((string) Storage::disk($file->disk)->get($file->path), true);

    expect($contents['sections']['enrolment']['active_students'])->toBe(3)
        ->and($contents['sections']['financial'])->toHaveKeys(['lines', 'net_minor'])
        ->and($contents['sections'])->toHaveKeys(['staffing', 'boarding']);
});

it('refuses a board pack section with no real resolver rather than fabricating one', function (): void {
    $f = int02Fixture();
    $term = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create();
    $user = User::factory()->create();

    expect(fn () => app(GenerateBoardPackAction::class)->execute($f['school']->id, $term->id, ['risk_summary'], $user->id))
        ->toThrow(InvalidArgumentException::class);
});

it('registers the executive persona\'s KPI widget through COM-03\'s own widget resolver, not a second mechanism (BR-INT-02-001/007)', function (): void {
    $f = int02Fixture();
    Invoice::factory()->for($f['school'])->create(['net_minor' => 10000, 'paid_minor' => 10000, 'balance_minor' => 0]);
    $user = User::factory()->create();

    $widgets = app(BuildExecutiveDashboardAction::class)->execute($user, $f['school']->id);

    $collectionWidget = collect($widgets)->firstWhere('key', 'collection_rate_executive');
    expect($collectionWidget)->not->toBeNull()
        ->and($collectionWidget->summary['status'])->toBe('green');
});

it('reads a multi-year enrolment comparative from warehouse snapshots, never a live cross-year aggregation (AC-INT-02-004)', function (): void {
    $f = int02Fixture();
    Student::factory()->for($f['school'])->count(5)->create();

    WarehouseSnapshot::factory()->for($f['school'])->create([
        'entity_key' => 'student', 'snapshot_date' => '2024-06-15', 'row_count' => 400,
    ]);
    WarehouseSnapshot::factory()->for($f['school'])->create([
        'entity_key' => 'student', 'snapshot_date' => '2025-06-15', 'row_count' => 420,
    ]);

    $comparative = app(GetEnrolmentComparativeAction::class)->execute($f['school']->id, [2024, 2025, 2026]);

    expect($comparative[0])->toBe(['year' => 2024, 'snapshotDate' => '2024-06-15', 'studentCount' => 400])
        ->and($comparative[1])->toBe(['year' => 2025, 'snapshotDate' => '2025-06-15', 'studentCount' => 420])
        ->and($comparative[2]['studentCount'])->toBeNull();
});
