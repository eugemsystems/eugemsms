<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Sessions\RunPeriodCloseChecklistAction;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * `Core\Sessions\CloseChecklist` (Book A CORE-03 §5). Read-only preview
 * of `RunPeriodCloseChecklistAction` — the same registry
 * `TransitionPeriodStateAction` itself gates `SoftClosed → Locked` on,
 * so what passes here is exactly what will let the lock through.
 */
#[Title('Close checklist')]
#[Layout('layouts.app')]
final class CloseChecklist extends Component
{
    use InteractsWithSchool;
    use InteractsWithSession;

    public Term $term;

    public PeriodType $type;

    public function mount(School $school, Term $term, string $periodType): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);

        abort_unless($term->school_id === $school->id, 404);

        $this->term = $term;
        $this->type = PeriodType::tryFrom($periodType) ?? abort(404);
    }

    public function render(): View
    {
        return view('core::sessions.close-checklist', [
            'result' => app(RunPeriodCloseChecklistAction::class)->execute($this->term, $this->type),
        ]);
    }
}
