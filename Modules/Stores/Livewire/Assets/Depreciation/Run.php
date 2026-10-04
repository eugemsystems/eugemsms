<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Depreciation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ApproveDepreciationRunAction;
use Modules\Stores\Domain\Actions\PostDepreciationRunAction;
use Modules\Stores\Domain\Actions\PreviewDepreciationRunAction;
use Modules\Stores\Domain\DataObjects\PreviewDepreciationRunData;
use Modules\Stores\Models\DepreciationRun;

/**
 * `Assets\Depreciation\Run` (Book H1 FIN-10 §5, `assets.depreciation.run`).
 * Preview → approve → post, one journal per run grouped by category
 * and cost centre — never a second stored balance. `PostDepreciationRunAction`
 * is the ONLY place `fixed_assets.net_book_value_minor`/
 * `accumulated_depreciation_minor` move; this screen never writes
 * either column itself.
 */
#[Title('Depreciation run')]
#[Layout('layouts.app')]
final class Run extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $periodMonth;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('assets.depreciation.run');
        $this->periodMonth = now()->format('Y-m');
    }

    public function preview(): void
    {
        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(PreviewDepreciationRunAction::class)->execute(new PreviewDepreciationRunData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                periodMonth: $this->periodMonth,
                computedByUserId: (int) auth()->id(),
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->toast(__('Preview computed.'));
    }

    public function approve(int $runId): void
    {
        try {
            app(ApproveDepreciationRunAction::class)->execute($runId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Run approved.'));
    }

    public function post(int $runId): void
    {
        try {
            app(PostDepreciationRunAction::class)->execute($runId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Depreciation posted.'));
    }

    public function render(): View
    {
        return view('stores::assets.depreciation.run', [
            'runs' => DepreciationRun::where('school_id', $this->school->id)->orderByDesc('period_month')->limit(24)->get(),
        ]);
    }
}
