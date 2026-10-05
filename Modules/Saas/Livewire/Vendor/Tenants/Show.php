<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Tenants;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Scopes\SchoolScope;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\ComputeTenantHealthSnapshotAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SupportTicket;
use Modules\Saas\Models\TenantHealthSnapshot;

/**
 * `Saas\Tenants\Show` (Book J SAA-02 §4, vendor console). One tenant's full
 * health snapshot with every component signal, its history, subscription,
 * schools, support history and the vendor-console audit trail against it
 * (BR-SAA-02-007). Support tickets are read across schools — a deliberate,
 * vendor-only bypass of the school scope, taken only after the console gate.
 * Opening a tenant is itself recorded.
 */
#[Title('Tenant')]
#[Layout('saas::layouts.vendor')]
final class Show extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public int $tenantId;

    public function mount(int $tenant): void
    {
        $operator = $this->authorizeVendor();

        $record = Tenant::query()->findOrFail($tenant);
        $this->tenantId = $record->id;

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'tenant.viewed', "Viewed tenant {$record->name}", $record->id);
    }

    public function recompute(): void
    {
        $operator = $this->authorizeVendor();

        $tenant = Tenant::query()->findOrFail($this->tenantId);
        app(ComputeTenantHealthSnapshotAction::class)->execute($tenant->id);

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'tenant.health_recomputed', "Recomputed health for {$tenant->name}", $tenant->id);

        $this->toast(__('Health recomputed.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $history = TenantHealthSnapshot::query()->where('tenant_id', $tenant->id)->orderByDesc('snapshot_date')->limit(30)->get();

        return view('saas::vendor.tenant-show', [
            'tenant' => $tenant,
            'snapshot' => $history->first(),
            'history' => $history,
            'subscription' => Subscription::query()->with('plan')->where('tenant_id', $tenant->id)->latest('id')->first(),
            'schools' => School::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name', 'status']),
            'tickets' => SupportTicket::query()->withoutGlobalScope(SchoolScope::class)->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(25)->get(['id', 'subject', 'priority', 'status', 'sla_due_at', 'created_at']),
            'audit' => ActivityLogEntry::query()->where('log_name', 'vendor_console')->where('subject_type', 'tenant')->where('subject_id', $tenant->id)->orderByDesc('id')->limit(25)->get(['id', 'event', 'description', 'causer_id', 'ip_address', 'created_at']),
        ]);
    }
}
