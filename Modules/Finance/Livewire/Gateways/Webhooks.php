<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Gateways;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ReprocessGatewayWebhookAction;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\GatewayWebhook;

/**
 * `Finance\Gateways\Webhooks` (Book B FIN-05 §6/§8, `finance.gateway.view`).
 * Raw payload viewer + "Reprocess" for a `failed` row — see
 * `ReprocessGatewayWebhookAction`'s own docblock for why that needed a
 * new Action: re-submitting the SAME payload through
 * `IngestGatewayWebhookAction` only ever returns the existing row
 * untouched (BR-FIN-05-004's replay protection fires first).
 */
#[Title('Webhook log')]
#[Layout('layouts.app')]
final class Webhooks extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use ResolvesSystemAccounts;
    use Toasts;

    public ?int $viewingWebhookId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.gateway.view');
    }

    public function viewPayload(int $webhookId): void
    {
        $this->viewingWebhookId = $webhookId;
    }

    public function closePayload(): void
    {
        $this->viewingWebhookId = null;
    }

    public function reprocess(int $webhookId): void
    {
        try {
            app(ReprocessGatewayWebhookAction::class)->execute(
                gatewayWebhookId: $webhookId,
                processedByUserId: (int) Auth::id(),
                creditBalanceAccountId: $this->requireSystemAccount('credit_balance', __('Credit Balance')),
                suspenseAccountId: $this->requireSystemAccount('suspense', __('Suspense')),
            );
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Webhook reprocessed.'));
    }

    public function render(): View
    {
        $query = GatewayWebhook::query()
            ->where(fn ($q) => $q->where('school_id', $this->school->id)->orWhereNull('school_id'))
            ->orderByDesc('received_at');

        return view('finance::gateways.webhooks', [
            'webhooks' => $this->paginateDataTable($query, $this->tableColumns()),
            'viewingWebhook' => $this->viewingWebhookId !== null ? GatewayWebhook::find($this->viewingWebhookId) : null,
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'driver' => ['label' => __('Driver'), 'sortable' => true],
            'event_type' => ['label' => __('Event'), 'sortable' => true],
            'signature_valid' => [
                'label' => __('Signature'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Valid'), '0' => __('Invalid')],
            ],
            'processing_status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['received' => __('Received'), 'processed' => __('Processed'), 'ignored' => __('Ignored'), 'failed' => __('Failed'), 'duplicate' => __('Duplicate')],
            ],
            'received_at' => ['label' => __('Received'), 'sortable' => true],
        ];
    }
}
