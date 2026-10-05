<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Retention;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\CheckRetentionScheduleCoverageAction;
use Modules\Compliance\Domain\Actions\CreateRetentionScheduleAction;
use Modules\Compliance\Domain\DataObjects\CreateRetentionScheduleData;
use Modules\Compliance\Models\RetentionSchedule;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Retention` (Book H3 CMP-03 §4 ⚠, `privacy.manage`).
 * The nightly registry check (`CheckRetentionScheduleCoverageAction`,
 * BR-CMP-03-005 ⭐/AC-CMP-03-006) is exposed as an on-demand button
 * here, the same honest stand-in for a scheduled job this book uses
 * throughout. `record_class` includes `safeguarding_record` in the
 * spec's own enum — this screen only ever shows/creates the SCHEDULE
 * (record class, table names, retention years, trigger, disposal
 * method, legal basis), never any actual record's content, so no
 * extra gating is needed here beyond the standard permission check.
 */
#[Title('Retention schedules')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $recordClass = 'academic_record';

    public string $tableNamesText = '';

    public string $retentionYears = '7';

    public string $retentionTrigger = 'record_created';

    public string $disposalMethod = 'anonymise';

    public string $legalBasis = '';

    public bool $requiresReview = true;

    /** @var array<string, mixed>|null */
    public ?array $coverageResult = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->validate([
            'recordClass' => ['required', 'string', 'max:60'],
            'tableNamesText' => ['required', 'string'],
            'retentionYears' => ['required', 'numeric', 'min:0'],
            'retentionTrigger' => ['required', 'in:record_created,learner_exit,staff_exit,case_closed'],
            'disposalMethod' => ['required', 'in:delete,anonymise,archive'],
            'legalBasis' => ['required', 'string', 'max:255'],
        ]);

        $tableNames = array_values(array_filter(array_map('trim', explode(',', $this->tableNamesText))));

        app(CreateRetentionScheduleAction::class)->execute(new CreateRetentionScheduleData(
            schoolId: $this->school->id,
            recordClass: $this->recordClass,
            tableNames: $tableNames,
            retentionYears: $this->retentionYears,
            retentionTrigger: $this->retentionTrigger,
            disposalMethod: $this->disposalMethod,
            legalBasis: $this->legalBasis,
            requiresReview: $this->requiresReview,
        ));

        $this->reset(['tableNamesText', 'legalBasis']);
        $this->toast(__('Retention schedule created.'));
    }

    public function checkCoverage(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->coverageResult = app(CheckRetentionScheduleCoverageAction::class)->execute($this->school->id);
    }

    public function render(): View
    {
        return view('compliance::privacy.retention.index', [
            'schedules' => RetentionSchedule::where('school_id', $this->school->id)->orderBy('record_class')->get(),
        ]);
    }
}
