<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Notifications;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Notifications\CreateOptOutAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateOptOutData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\NotificationOptOut;
use Modules\Core\Models\School;

/**
 * `Core\Notifications\OptOuts` (Book A CORE-09 §5, `core.notification.view`)
 * — the opt-out register. Most rows arrive automatically (a hard bounce
 * via `RecordDeliveryStatusAction`); this screen also lets an admin add
 * one manually for a `user_request` (a parent calling in to ask).
 */
#[Title('Notification opt-outs')]
#[Layout('layouts.app')]
final class OptOuts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $address = '';

    public string $channel = 'sms';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.notification.view');
    }

    public function openCreateModal(): void
    {
        $this->reset(['address', 'channel']);
        $this->channel = 'sms';
        $this->showCreateModal = true;
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->validate([
            'address' => ['required', 'string', 'max:200'],
            'channel' => ['required', Rule::in(['sms', 'whatsapp', 'email', 'push', 'in_app'])],
        ]);

        app(CreateOptOutAction::class)->execute(new CreateOptOutData(
            schoolId: $this->school->id,
            address: $this->address,
            channel: $this->channel,
            reason: 'user_request',
        ));

        $this->showCreateModal = false;
        $this->toast(__('Opt-out recorded.'));
    }

    public function render(): View
    {
        $query = NotificationOptOut::query()->where('school_id', $this->school->id);

        return view('core::notifications.opt-outs', [
            'optOuts' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'address' => ['label' => __('Address'), 'sortable' => true, 'searchable' => true],
            'channel' => ['label' => __('Channel'), 'sortable' => true],
            'reason' => [
                'label' => __('Reason'), 'sortable' => true, 'filter' => 'select',
                'options' => ['user_request' => __('User request'), 'bounce' => __('Bounce'), 'complaint' => __('Complaint'), 'invalid' => __('Invalid')],
            ],
            'opted_out_at' => ['label' => __('Opted out'), 'sortable' => true],
        ];
    }
}
