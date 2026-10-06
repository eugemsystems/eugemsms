<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Cbt;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AutoSubmitExpiredAttemptsAction;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtTest;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Academic\Cbt\Monitor` (Book K ACA-09 §5, `cbt.test.monitor`). Live view
 * of a test: who is in progress, the remaining time computed on the SERVER
 * (BR-ACA-09-002), the last autosave and any extra time from an approved
 * arrangement. A tab-switch count above the limit is shown as a flag for a
 * person to review — never a disqualification (BR-ACA-09-006). Read-only
 * apart from submitting attempts whose time has run out.
 */
#[Title('Test monitor')]
#[Layout('layouts.app')]
final class Monitor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $testId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('cbt.test.monitor');

        $this->testId = CbtTest::query()->whereIn('status', ['open', 'scheduled'])->orderByDesc('opens_at')->value('id') ?? CbtTest::query()->orderByDesc('id')->value('id');
    }

    public function submitExpired(): void
    {
        $this->authorizePermission('cbt.test.monitor');

        $test = CbtTest::query()->findOrFail($this->testId);
        $count = app(AutoSubmitExpiredAttemptsAction::class)->execute($test->id);

        $this->toast(__(':count attempt(s) past their time limit were submitted with what they had saved.', ['count' => $count]));
    }

    public function render(): View
    {
        $test = $this->testId === null ? null : CbtTest::query()->find($this->testId);
        $attempts = $test === null ? collect() : CbtCandidateAttempt::query()->with('test')->where('test_id', $test->id)->orderBy('student_id')->get();
        $limit = $test?->max_tab_switches;

        return view('academic::cbt.monitor', [
            'tests' => CbtTest::query()->orderByDesc('id')->limit(30)->get(['id', 'title', 'status']),
            'test' => $test,
            'attempts' => $attempts,
            'students' => Student::query()->whereIn('id', $attempts->pluck('student_id'))->get()->keyBy('id'),
            'inProgress' => $attempts->whereIn('status', ['in_progress', 'flagged'])->count(),
            'limit' => $limit,
        ]);
    }
}
