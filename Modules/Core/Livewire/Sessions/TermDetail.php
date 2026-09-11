<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Sessions\GenerateTermWeeksAction;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * `Core\Sessions\TermDetail` (Book A CORE-03 §5). One term's dates, week
 * calendar, and current academic/financial state, with links out to
 * `PeriodControl`/`CloseChecklist` for each period type — this screen
 * itself never writes `academic_state`/`financial_state`
 * (`TransitionPeriodStateAction` is the only gateway for that).
 */
#[Title('Term detail')]
#[Layout('layouts.app')]
final class TermDetail extends Component
{
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public Term $term;

    public function mount(School $school, Term $term): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);

        abort_unless($term->school_id === $school->id, 404);

        $this->term = $term;
    }

    public function generateWeeks(): void
    {
        try {
            app(GenerateTermWeeksAction::class)->execute($this->term);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->term->refresh();

        $this->toast(__('Term weeks generated.'));
    }

    public function render(): View
    {
        return view('core::sessions.term-detail', [
            'weeks' => $this->term->weeks()->orderBy('week_number')->get(),
            'periodTypes' => PeriodType::cases(),
        ]);
    }
}
