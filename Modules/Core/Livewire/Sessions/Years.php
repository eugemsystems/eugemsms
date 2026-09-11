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
use Modules\Core\Domain\Actions\Sessions\CreateTermAction;
use Modules\Core\Domain\DataObjects\Sessions\CreateTermData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * `Core\Sessions\Years` (Book A CORE-03 §5). Academic years for a school,
 * with an inline terms panel for whichever year is selected — a year has
 * no screen of its own beyond this list, since everything else a year
 * needs (its terms) is reached from here. `core.session.view`/
 * `core.session.manage` are not yet enforced (CORE-05 gap, same as
 * `InteractsWithSchool`).
 */
#[Title('Academic years')]
#[Layout('layouts.app')]
final class Years extends Component
{
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $selectedYearId = null;

    public bool $showTermModal = false;

    public string $termName = '';

    public int $termNumber = 1;

    public string $termStartsOn = '';

    public string $termEndsOn = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
    }

    public function selectYear(int $yearId): void
    {
        $this->selectedYearId = $this->selectedYearId === $yearId ? null : $yearId;
    }

    public function openTermModal(): void
    {
        if ($this->selectedYearId === null) {
            return;
        }

        $this->showTermModal = true;
    }

    public function createTerm(): void
    {
        if ($this->selectedYearId === null) {
            return;
        }

        try {
            app(CreateTermAction::class)->execute(new CreateTermData(
                schoolId: $this->school->id,
                academicYearId: $this->selectedYearId,
                number: $this->termNumber,
                name: $this->termName,
                startsOn: Carbon::parse($this->termStartsOn),
                endsOn: Carbon::parse($this->termEndsOn),
                actingUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['termName', 'termNumber', 'termStartsOn', 'termEndsOn', 'showTermModal']);
        $this->termNumber = 1;

        $this->toast(__('Term created.'));
    }

    public function render(): View
    {
        $query = AcademicYear::query()->where('school_id', $this->school->id)->orderByDesc('starts_on');

        $terms = $this->selectedYearId !== null
            ? Term::query()->where('academic_year_id', $this->selectedYearId)->orderBy('number')->get()
            : collect();

        return view('core::sessions.years', [
            'years' => $this->paginateDataTable($query, $this->tableColumns()),
            'terms' => $terms,
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'name' => ['label' => __('Name'), 'sortable' => true, 'searchable' => true],
            'starts_on' => ['label' => __('Starts'), 'sortable' => true],
            'ends_on' => ['label' => __('Ends'), 'sortable' => true],
            'is_current' => [
                'label' => __('Current'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
            'academic_state' => [
                'label' => __('Academic state'), 'sortable' => true, 'filter' => 'select',
                'options' => $this->stateOptions(),
            ],
            'financial_state' => [
                'label' => __('Financial state'), 'sortable' => true, 'filter' => 'select',
                'options' => $this->stateOptions(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stateOptions(): array
    {
        return collect(PeriodState::cases())
            ->mapWithKeys(fn (PeriodState $state): array => [$state->value => __(ucfirst(str_replace('_', ' ', $state->value)))])
            ->all();
    }
}
