<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Library;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CompleteLibraryStockTakeAction;
use Modules\Academic\Domain\Actions\MarkConfirmatoryPassDoneAction;
use Modules\Academic\Domain\Actions\RecordStockTakeScanAction;
use Modules\Academic\Domain\Actions\StartLibraryStockTakeAction;
use Modules\Academic\Domain\DataObjects\CompleteLibraryStockTakeData;
use Modules\Academic\Domain\DataObjects\MarkConfirmatoryPassDoneData;
use Modules\Academic\Domain\DataObjects\RecordStockTakeScanData;
use Modules\Academic\Domain\DataObjects\StartLibraryStockTakeData;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;

/**
 * `Academic\Library\StockTake` (Book K ACA-10 §5, `library.stocktake`).
 * Scan-based reconciliation against the catalogue. Copies still unscanned
 * after a confirmatory second pass are marked lost on completion, and where
 * one was on an active loan its borrower is charged (BR-ACA-10-009).
 */
#[Title('Library stock-take')]
#[Layout('layouts.app')]
final class StockTake extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $scanCode = '';

    public ?int $feeComponentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('library.stocktake');
    }

    public function start(): void
    {
        $this->authorizePermission('library.stocktake');

        try {
            app(StartLibraryStockTakeAction::class)->execute(new StartLibraryStockTakeData($this->school->id));
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Stock-take started.'));
    }

    public function scan(): void
    {
        $this->authorizePermission('library.stocktake');
        $this->resetErrorBag();

        $stockTake = $this->current();
        $code = trim($this->scanCode);
        $copy = $code === '' ? null : LibraryCopy::query()->where(fn ($q) => $q->where('accession_number', $code)->orWhere('barcode', $code))->first();

        if ($stockTake === null || $copy === null) {
            $this->addError('scanCode', __('No such copy in this library.'));

            return;
        }

        try {
            app(RecordStockTakeScanAction::class)->execute(new RecordStockTakeScanData($stockTake->id, $copy->id));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('scanCode', $exception->getMessage());

            return;
        }

        $this->reset('scanCode');
    }

    public function confirmSecondPass(): void
    {
        $this->authorizePermission('library.stocktake');
        $stockTake = $this->current();

        if ($stockTake === null) {
            return;
        }

        try {
            app(MarkConfirmatoryPassDoneAction::class)->execute(new MarkConfirmatoryPassDoneData($stockTake->id));
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Second pass recorded.'));
    }

    public function complete(): void
    {
        $this->authorizePermission('library.stocktake');
        $this->resetErrorBag();

        $stockTake = $this->current();
        $componentId = $this->feeComponentId === null ? null : FeeComponent::query()->where('is_active', true)->whereKey($this->feeComponentId)->value('id');

        if ($stockTake === null) {
            return;
        }

        if ($componentId === null) {
            $this->addError('feeComponentId', __('Choose the fee component a borrower is charged to for a missing copy on loan.'));

            return;
        }

        try {
            $done = app(CompleteLibraryStockTakeAction::class)->execute(new CompleteLibraryStockTakeData($stockTake->id, (int) $componentId, (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('feeComponentId', $exception->getMessage());

            return;
        }

        $this->toast(trans_choice(':count copy marked lost.|:count copies marked lost.', $done->missing_count, ['count' => $done->missing_count]));
    }

    private function current(): ?LibraryStockTake
    {
        return LibraryStockTake::query()->where('status', 'in_progress')->latest('id')->first();
    }

    public function render(): View
    {
        $current = $this->current();
        $unscanned = $current === null ? collect() : LibraryCopy::query()->with('item')->where('status', '!=', 'withdrawn')->whereNotIn('id', $current->scanned_copy_ids ?? [])->orderBy('accession_number')->limit(200)->get();

        return view('academic::library.stock-take', [
            'current' => $current,
            'unscanned' => $unscanned,
            'past' => LibraryStockTake::query()->where('status', '!=', 'in_progress')->orderByDesc('id')->limit(8)->get(),
            'components' => FeeComponent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
