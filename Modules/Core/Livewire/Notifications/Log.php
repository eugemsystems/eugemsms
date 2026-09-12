<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;

/**
 * `Core\Notifications\Log` (Book A CORE-09 §5, `core.notification.view`)
 * — every notification for this school, whatever its status/channel.
 */
#[Title('Notification log')]
#[Layout('layouts.app')]
final class Log extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.notification.view');
    }

    public function render(): View
    {
        $query = Notification::query()->where('school_id', $this->school->id);

        return view('core::notifications.log', [
            'notifications' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'notification_key' => ['label' => __('Key'), 'sortable' => true, 'searchable' => true],
            'channel' => [
                'label' => __('Channel'), 'sortable' => true, 'filter' => 'select',
                'options' => ['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => __('Email'), 'push' => __('Push'), 'in_app' => __('In-app')],
            ],
            'recipient_address' => ['label' => __('Recipient'), 'searchable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'queued' => __('Queued'), 'sending' => __('Sending'), 'sent' => __('Sent'), 'delivered' => __('Delivered'),
                    'read' => __('Read'), 'failed' => __('Failed'), 'bounced' => __('Bounced'), 'suppressed' => __('Suppressed'),
                ],
            ],
            'created_at' => ['label' => __('Created'), 'sortable' => true],
        ];
    }
}
