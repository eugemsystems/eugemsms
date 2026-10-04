<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Budget\Virement;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ApproveVirementAction;
use Modules\Stores\Domain\Actions\RequestVirementAction;
use Modules\Stores\Domain\DataObjects\RequestVirementData;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\BudgetVirement;

/**
 * `Budget\Virement\Create` (Book H1 FIN-11 §5, `budget.virement.request`
 * to request, approval is the same single-gate shape every other
 * module in this book uses). A locked line refuses virement in either
 * direction — `RequestVirementAction`/`ApproveVirementAction` both
 * check it independently (BR-FIN-11-011).
 */
#[Title('Virement')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $budgetId = null;

    public ?int $fromLineId = null;

    public ?int $toLineId = null;

    public string $amountMinor = '';

    public string $reason = '';

    public string $effectiveFrom;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('budget.virement.request');
        $this->effectiveFrom = now()->toDateString();
    }

    public function request(): void
    {
        $this->validate([
            'budgetId' => ['required', 'integer'],
            'fromLineId' => ['required', 'integer'],
            'toLineId' => ['required', 'integer', 'different:fromLineId'],
            'amountMinor' => ['required', 'integer', 'gt:0'],
            'reason' => ['required', 'string'],
        ]);

        try {
            app(RequestVirementAction::class)->execute(new RequestVirementData(
                budgetId: (int) $this->budgetId,
                fromLineId: (int) $this->fromLineId,
                toLineId: (int) $this->toLineId,
                amountMinor: (int) $this->amountMinor,
                reason: $this->reason,
                requestedByUserId: (int) auth()->id(),
                effectiveFrom: Carbon::parse($this->effectiveFrom),
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->reset(['amountMinor', 'reason']);
        $this->toast(__('Virement requested.'));
    }

    public function approve(int $virementId): void
    {
        try {
            app(ApproveVirementAction::class)->execute($virementId, (int) auth()->id());
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Virement approved.'));
    }

    public function render(): View
    {
        return view('stores::budget.virement.create', [
            'budgetLines' => BudgetLine::where('school_id', $this->school->id)->orderBy('id')->limit(200)->get(),
            'pending' => BudgetVirement::where('school_id', $this->school->id)->where('status', 'pending')->get(),
        ]);
    }
}
