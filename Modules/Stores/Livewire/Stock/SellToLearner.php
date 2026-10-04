<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Stock;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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
use Modules\People\Models\Student;
use Modules\Stores\Domain\Actions\IssueSaleableItemToLearnerAction;
use Modules\Stores\Domain\DataObjects\IssueSaleableItemToLearnerData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * `Stores\Stock\SellToLearner` (Book H1 FIN-09 §6, `inventory.issue`,
 * AC-FIN-09-010). A uniform/textbook issue to a learner is a sale, not
 * a requisition — the revenue side raises an ad hoc `FIN-02` charge and
 * cost of sales posts separately, both inside the one Action call this
 * screen makes.
 */
#[Title('Sell to learner')]
#[Layout('layouts.app')]
final class SellToLearner extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $storeId = null;

    public ?int $itemId = null;

    public ?int $studentId = null;

    public string $quantity = '1';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('inventory.issue');
    }

    public function sell(): void
    {
        $this->validate([
            'storeId' => ['required', 'integer'],
            'itemId' => ['required', 'integer'],
            'studentId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(IssueSaleableItemToLearnerAction::class)->execute(new IssueSaleableItemToLearnerData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                storeId: (int) $this->storeId,
                itemId: (int) $this->itemId,
                studentId: (int) $this->studentId,
                quantity: (float) $this->quantity,
                issuedByUserId: (int) auth()->id(),
                issuedAt: Carbon::now(),
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['quantity']);
        $this->toast(__('Sold — charge raised and cost of sales posted.'));
    }

    public function render(): View
    {
        return view('stores::stock.sell-to-learner', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->where('is_saleable', true)->orderBy('name')->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
        ]);
    }
}
