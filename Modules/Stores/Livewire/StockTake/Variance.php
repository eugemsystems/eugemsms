<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\StockTake;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Domain\Actions\ApproveStockTakeVarianceAction;
use Modules\Stores\Domain\Actions\RecordRecountAction;
use Modules\Stores\Domain\Actions\RecordStockTakeVarianceReasonAction;
use Modules\Stores\Models\StockTake;

/**
 * `Stores\StockTake\Variance` (Book H1 FIN-09 §7, `inventory.stocktake.approve` ⚠).
 * Refuses to approve while any line still needs a recount
 * (BR-FIN-09-015) — `ApproveStockTakeVarianceAction` itself enforces
 * this; the screen surfaces the refusal rather than pre-computing it,
 * so the Action stays the single source of truth for "is this
 * approvable".
 */
#[Title('Stock take variance')]
#[Layout('layouts.app')]
final class Variance extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $stockTakeId = null;

    /** @var array<int, string> lineId => recount quantity */
    public array $recounts = [];

    /** @var array<int, string> lineId => reason */
    public array $reasons = [];

    public ?int $shrinkageAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.stocktake.approve');
    }

    public function saveReason(int $lineId): void
    {
        $reason = $this->reasons[$lineId] ?? '';
        app(RecordStockTakeVarianceReasonAction::class)->execute($lineId, $reason !== '' ? $reason : null);
        $this->toast(__('Reason saved.'));
    }

    public function recount(int $lineId): void
    {
        $value = $this->recounts[$lineId] ?? null;

        if ($value === null || $value === '') {
            return;
        }

        try {
            app(RecordRecountAction::class)->execute($lineId, (float) $value, (int) auth()->id());
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->toast(__('Recount recorded.'));
    }

    public function approve(): void
    {
        $this->validate(['stockTakeId' => ['required', 'integer'], 'shrinkageAccountId' => ['required', 'integer']]);

        $take = StockTake::findOrFail($this->stockTakeId);

        try {
            app(ApproveStockTakeVarianceAction::class)->execute(
                (int) $this->stockTakeId,
                (int) auth()->id(),
                (int) $this->shrinkageAccountId,
                $take->term->academic_year_id,
            );
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Variance approved — adjustment journal posted.'));
    }

    public function render(): View
    {
        $lines = collect();

        if ($this->stockTakeId !== null) {
            $take = StockTake::with('lines.item')->find($this->stockTakeId);

            if ($take !== null) {
                $lines = $take->lines;
            }
        }

        return view('stores::stocktake.variance', [
            'takes' => StockTake::where('school_id', $this->school->id)
                ->whereIn('status', ['counting', 'variance_review'])
                ->get(),
            'lines' => $lines,
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
