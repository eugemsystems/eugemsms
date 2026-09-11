<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions;

use App\Concerns\Toasts;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Sessions\CreateAcademicYearAction;
use Modules\Core\Domain\DataObjects\Sessions\CreateYearData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `Core\Sessions\YearWizard` (Book A CORE-03 §5). Creates one academic
 * year, optionally splitting it into three even terms
 * (BR-CORE-03-003 default) via `CreateAcademicYearAction`.
 */
#[Title('New academic year')]
#[Layout('layouts.app')]
final class YearWizard extends Component
{
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $name = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $generateThreeTerms = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
    }

    public function create(): void
    {
        try {
            app(CreateAcademicYearAction::class)->execute(new CreateYearData(
                schoolId: $this->school->id,
                name: $this->name,
                startsOn: Carbon::parse($this->startsOn),
                endsOn: Carbon::parse($this->endsOn),
                actingUserId: (int) Auth::id(),
                generateThreeTerms: $this->generateThreeTerms,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Academic year created.'));

        $this->redirect(route('sessions.years', $this->school), navigate: true);
    }

    public function render(): View
    {
        return view('core::sessions.year-wizard');
    }
}
