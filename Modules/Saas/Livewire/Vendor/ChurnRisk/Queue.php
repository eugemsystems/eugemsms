<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\ChurnRisk;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\RaiseChurnRiskFlagAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\Actions\ReviewChurnRiskFlagAction;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\ChurnRiskFlag;

/**
 * `Success\ChurnRisk\Queue` (Book J SAA-03 §5, vendor console). A churn flag
 * is shown with its contributing factors in plain language, weights and
 * sources — never as a bare score (BR-SAA-03-008, AC-SAA-03-005). Flags are
 * advisory: a person moves one along; nothing contacts the customer.
 */
#[Title('Churn risk')]
#[Layout('saas::layouts.vendor')]
final class Queue extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $statusFilter = 'open';

    public ?int $tenantId = null;

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function evaluate(): void
    {
        $operator = $this->authorizeVendor();

        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            $this->toast(__('Choose a tenant first.'), 'danger');

            return;
        }

        $flag = app(RaiseChurnRiskFlagAction::class)->execute($tenant->id);

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'churn.evaluated', "Churn risk evaluated for {$tenant->name}", $tenant->id, ['raised' => $flag !== null]);

        $this->toast($flag === null ? __('No new flag — below the threshold, or one is already open.') : __('A churn risk flag was raised.'));
    }

    public function move(int $flagId, string $status): void
    {
        $operator = $this->authorizeVendor();

        $flag = ChurnRiskFlag::query()->findOrFail($flagId);

        try {
            app(ReviewChurnRiskFlagAction::class)->execute($flag->id, $status);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'churn.reviewed', "Churn flag {$flag->id}: {$flag->status} → {$status}", $flag->tenant_id, ['flag_id' => $flag->id]);

        $this->toast(__('Flag updated.'));
    }

    public function assign(int $flagId, int $vendorUserId): void
    {
        $operator = $this->authorizeVendor();

        $flag = ChurnRiskFlag::query()->findOrFail($flagId);

        try {
            app(ReviewChurnRiskFlagAction::class)->execute($flag->id, null, $vendorUserId);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'churn.assigned', "Churn flag {$flag->id} assigned", $flag->tenant_id, ['flag_id' => $flag->id, 'assignee' => $vendorUserId]);

        $this->toast(__('Assigned.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $flags = ChurnRiskFlag::query()
            ->when(in_array($this->statusFilter, ['open', 'intervention_logged', 'resolved', 'churned'], true), fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('flagged_at')->limit(100)->get();

        return view('saas::vendor.churn', [
            'flags' => $flags,
            'tenantNames' => Tenant::query()->whereIn('id', $flags->pluck('tenant_id'))->pluck('name', 'id'),
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'staff' => User::query()->where('user_type', UserType::Vendor)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
