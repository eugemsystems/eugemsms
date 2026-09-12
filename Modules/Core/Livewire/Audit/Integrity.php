<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Audit;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Audit\RunIntegrityChecksAction;
use Modules\Core\Domain\DataObjects\Audit\RunIntegrityChecksData;
use Modules\Core\Domain\Registry\IntegrityCheckRegistry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\IntegrityCheckRun;
use Modules\Core\Models\School;

/**
 * `Core\Audit\Integrity` (Book A CORE-08 §4/§5, `core.audit.view`) —
 * last run, status, and failures for every registered check. The
 * nightly/weekly schedule itself is a later wave (same as every other
 * CORE background job in Book A) — "Run now" is how this check suite
 * gets exercised until that scheduler exists.
 */
#[Title('Integrity dashboard')]
#[Layout('layouts.app')]
final class Integrity extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.audit.view');
    }

    public function runChecks(): void
    {
        $runs = app(RunIntegrityChecksAction::class)->execute(new RunIntegrityChecksData(schoolId: $this->school->id));

        $failed = collect($runs)->filter(fn (IntegrityCheckRun $run): bool => ! $run->passed())->count();

        $this->toast(
            $failed === 0
                ? __(':count check(s) run — all passed.', ['count' => count($runs)])
                : __(':count of :total check(s) failed — see the details below.', ['count' => $failed, 'total' => count($runs)]),
            $failed === 0 ? 'success' : 'danger',
        );
    }

    public function render(): View
    {
        $latestRuns = IntegrityCheckRun::query()
            ->where('school_id', $this->school->id)
            ->orderByDesc('ran_at')
            ->get()
            ->unique('check_type')
            ->keyBy('check_type');

        $checks = collect(IntegrityCheckRegistry::all())
            ->filter(fn ($check): bool => $check->isAvailable())
            ->map(fn ($check, string $type) => [
                'type' => $type,
                'lastRun' => $latestRuns->get($type),
            ])
            ->values();

        return view('core::audit.integrity', ['checks' => $checks]);
    }
}
