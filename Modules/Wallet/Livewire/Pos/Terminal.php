<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\Pos;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\TillSession;
use Modules\People\Models\Student;
use Modules\Wallet\Domain\Actions\ProcessWalletSaleAction;
use Modules\Wallet\Domain\Actions\SyncOfflineWalletSaleAction;
use Modules\Wallet\Domain\Actions\VoidWalletSaleAction;
use Modules\Wallet\Domain\DataObjects\ProcessWalletSaleData;
use Modules\Wallet\Domain\DataObjects\VoidWalletSaleData;
use Modules\Wallet\Models\SpendPoint;
use Modules\Wallet\Models\WalletProduct;
use Modules\Wallet\Models\WalletSale;

/**
 * `Wallet\Pos\Terminal` (Book H3 FIN-14 §4 ⭐/§6, `wallet.sell`).
 * Spending controls and category blocks are enforced entirely
 * server-side inside `ProcessWalletSaleAction` — this screen never
 * pre-checks a limit itself, it only surfaces the Action's own
 * refusal as a toast (BR-FIN-14-004/005). The "sale happened
 * offline" toggle is an honest, explicit stand-in for the spec's own
 * device-level offline cache/queue (§4) — ticking it routes the same
 * sale through `SyncOfflineWalletSaleAction` with a client-generated
 * `offline_reference` instead of `ProcessWalletSaleAction` directly,
 * which is exactly the one real behavioural difference between the
 * two paths (an offline sync is honoured even against an
 * insufficient balance, going negative and notifying the guardian,
 * BR-FIN-14-012) — there is no real browser-side offline cache/service
 * worker built in this pass. Also hosts void
 * (`VoidWalletSaleAction`) for the operator's own recent sales.
 */
#[Title('Tuckshop POS')]
#[Layout('layouts.app')]
final class Terminal extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $spendPointId = null;

    public ?int $studentId = null;

    public string $paymentMethod = 'wallet';

    public ?int $tillSessionId = null;

    public ?int $productId = null;

    public string $quantity = '1';

    /** @var array<int, array{product_id: int, quantity: float, name: string}> */
    public array $cart = [];

    public bool $offlineMode = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('wallet.sell');
    }

    public function addLine(): void
    {
        $this->validate([
            'productId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = WalletProduct::findOrFail($this->productId);

        $this->cart[] = ['product_id' => $product->id, 'quantity' => (float) $this->quantity, 'name' => $product->name];
        $this->reset(['productId', 'quantity']);
        $this->quantity = '1';
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function submitSale(): void
    {
        $this->validate(['spendPointId' => ['required', 'integer']]);

        if ($this->cart === []) {
            $this->toast(__('Add at least one line.'), 'danger');

            return;
        }

        $lines = array_map(fn (array $line): array => ['product_id' => $line['product_id'], 'quantity' => $line['quantity']], $this->cart);

        $data = new ProcessWalletSaleData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            spendPointId: (int) $this->spendPointId,
            paymentMethod: $this->paymentMethod,
            lines: $lines,
            operatorId: (int) auth()->id(),
            studentId: $this->paymentMethod === 'wallet' ? $this->studentId : null,
            tillSessionId: $this->paymentMethod === 'cash' ? $this->tillSessionId : null,
            deviceSource: $this->offlineMode ? 'offline_sync' : 'pos',
            offlineReference: $this->offlineMode ? (string) Str::uuid() : null,
        );

        try {
            if ($this->offlineMode) {
                app(SyncOfflineWalletSaleAction::class)->execute($data);
            } else {
                app(ProcessWalletSaleAction::class)->execute($data);
            }
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['cart', 'studentId']);
        $this->toast(__('Sale completed.'));
    }

    public function voidSale(int $saleId): void
    {
        app(VoidWalletSaleAction::class)->execute(new VoidWalletSaleData(
            saleId: $saleId,
            reason: 'Voided at POS by operator.',
            voidedByUserId: (int) auth()->id(),
        ));

        $this->toast(__('Sale voided.'));
    }

    public function render(): View
    {
        return view('wallet::pos.terminal', [
            'spendPoints' => SpendPoint::where('school_id', $this->school->id)->where('is_active', true)->get(),
            'products' => $this->spendPointId !== null
                ? WalletProduct::where('spend_point_id', $this->spendPointId)->where('is_active', true)->get()
                : collect(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(200)->get(),
            'tillSessions' => TillSession::where('school_id', $this->school->id)->where('status', 'open')->get(),
            'recentSales' => WalletSale::where('school_id', $this->school->id)->where('operator_id', auth()->id())
                ->where('status', 'completed')->orderByDesc('id')->limit(10)->get(),
        ]);
    }
}
