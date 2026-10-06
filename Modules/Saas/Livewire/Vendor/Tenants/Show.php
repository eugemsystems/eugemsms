<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Tenants;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\StartVendorImpersonationAction;
use Modules\Core\Domain\DataObjects\Auth\StartVendorImpersonationData;
use Modules\Core\Domain\Exceptions\ImpersonationNotPermittedException;
use Modules\Core\Domain\Scopes\SchoolScope;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\School;
use Modules\Core\Models\SupportAccessGrant;
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

    public string $userSearch = '';

    public ?int $targetUserId = null;

    public string $ticketReference = '';

    public string $reason = '';

    /**
     * Opens a read-only support session as one of the tenant's users (BR-SAA-02-002). Authorised by
     * `StartVendorImpersonationAction` — the customer's own, unexpired grant for this ticket — and
     * recorded in the vendor console audit. The browser then becomes that user; the persistent
     * banner and `EndImpersonationAction` swap back to the operator.
     */
    public function impersonate(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        if (session('impersonator_id') !== null) {
            $this->toast(__('A support session is already open — end it first.'), 'danger');

            return;
        }

        $this->validate(['targetUserId' => ['required', 'integer'], 'ticketReference' => ['required', 'string', 'max:60'], 'reason' => ['required', 'string', 'max:1000']]);

        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $target = User::query()->where('tenant_id', $tenant->id)->whereKey($this->targetUserId)->first();

        if ($target === null) {
            $this->addError('targetUserId', __('Choose one of this organisation\'s users.'));

            return;
        }

        try {
            $session = app(StartVendorImpersonationAction::class)->execute(new StartVendorImpersonationData($operator->id, $target->id, $this->ticketReference, $this->reason));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        } catch (ImpersonationNotPermittedException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'tenant.impersonation_started', "Opened a read-only support session as {$target->name} for ticket {$session->ticket_reference}", $tenant->id, ['impersonation_session_id' => $session->id, 'access_grant_id' => $session->access_grant_id]);

        session()->regenerate();
        session(['impersonator_id' => $operator->id, 'impersonation_session_id' => $session->id]);
        Auth::login($target);

        $this->redirect(route('dashboard'), navigate: false);
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
            'grants' => SupportAccessGrant::query()->where('tenant_id', $tenant->id)->whereNull('revoked_at')->where('expires_at', '>', now())->orderBy('expires_at')->get(['id', 'ticket_reference', 'expires_at']),
            'candidates' => User::query()->where('tenant_id', $tenant->id)->where('user_type', '!=', UserType::Vendor->value)
                ->when(trim($this->userSearch) !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', '%'.trim($this->userSearch).'%')->orWhere('email', 'like', '%'.trim($this->userSearch).'%')))
                ->orderBy('name')->limit(10)->get(['id', 'name', 'email']),
            'tickets' => SupportTicket::query()->withoutGlobalScope(SchoolScope::class)->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(25)->get(['id', 'subject', 'priority', 'status', 'sla_due_at', 'created_at']),
            'audit' => ActivityLogEntry::query()->where('log_name', 'vendor_console')->where('subject_type', 'tenant')->where('subject_id', $tenant->id)->orderByDesc('id')->limit(25)->get(['id', 'event', 'description', 'causer_id', 'ip_address', 'created_at']),
        ]);
    }
}
