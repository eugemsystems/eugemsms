<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Tenants;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\ComputeTenantHealthSnapshotAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\TenantHealthSnapshot;

/**
 * `Saas\Tenants\Index` (Book J SAA-02 §4, vendor console). Every tenant at a
 * glance — and, per AC-SAA-02-004, the health score is never a bare number:
 * each component signal that feeds it is a column beside it.
 */
#[Title('Tenants')]
#[Layout('saas::layouts.vendor')]
final class Index extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function recompute(int $tenantId): void
    {
        $operator = $this->authorizeVendor();

        $tenant = Tenant::query()->findOrFail($tenantId);
        app(ComputeTenantHealthSnapshotAction::class)->execute($tenant->id);

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'tenant.health_recomputed', "Recomputed health for {$tenant->name}", $tenant->id);

        $this->toast(__('Health recomputed.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $tenants = Tenant::query()
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderBy('name')->limit(200)->get();

        $latest = TenantHealthSnapshot::query()->whereIn('tenant_id', $tenants->pluck('id'))->orderByDesc('snapshot_date')->get()->unique('tenant_id')->keyBy('tenant_id');

        return view('saas::vendor.tenants-index', ['tenants' => $tenants, 'latest' => $latest]);
    }
}
