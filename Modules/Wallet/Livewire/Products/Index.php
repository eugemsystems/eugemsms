<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\Products;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Wallet\Domain\Actions\CreateWalletProductAction;
use Modules\Wallet\Domain\DataObjects\CreateWalletProductData;
use Modules\Wallet\Models\SpendPoint;
use Modules\Wallet\Models\WalletProduct;

/**
 * `Wallet\Products\Index` (Book H3 FIN-14 §2/§6, `wallet.manage`).
 * List + create, no `UpdateWalletProductAction` exists. `category`
 * is the exact string a guardian's `blocked_categories` matches
 * against (`ProcessWalletSaleAction::assertNoBlockedCategory`) — get
 * it right here.
 */
#[Title('Wallet products')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $spendPointId = null;

    public ?int $itemId = null;

    public string $code = '';

    public string $name = '';

    public string $category = '';

    public string $priceMinor = '';

    public string $currency = 'USD';

    public string $taxType = 'standard';

    public string $barcode = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('wallet.manage');
    }

    public function create(): void
    {
        $this->validate([
            'spendPointId' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:40'],
            'priceMinor' => ['required', 'integer', 'gt:0'],
            'currency' => ['required', 'in:USD,ZWG'],
            'taxType' => ['required', 'in:standard,zero_rated,exempt,withholding'],
        ]);

        app(CreateWalletProductAction::class)->execute(new CreateWalletProductData(
            schoolId: $this->school->id,
            spendPointId: (int) $this->spendPointId,
            code: $this->code,
            name: $this->name,
            category: $this->category,
            priceMinor: (int) $this->priceMinor,
            currency: $this->currency,
            taxType: $this->taxType,
            itemId: $this->itemId,
            barcode: $this->barcode !== '' ? $this->barcode : null,
        ));

        $this->reset(['code', 'name', 'priceMinor', 'barcode']);
        $this->toast(__('Product created.'));
    }

    public function render(): View
    {
        return view('wallet::products.index', [
            'products' => WalletProduct::where('school_id', $this->school->id)->with('spendPoint')->orderBy('code')->get(),
            'spendPoints' => SpendPoint::where('school_id', $this->school->id)->where('is_active', true)->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(200)->get(),
        ]);
    }
}
