<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Notifications;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Notifications\RetryNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\RetryNotificationData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;

/**
 * `Core\Notifications\Failures` (Book A CORE-09 §5, `core.notification.view`)
 * — failed/bounced notifications, with bulk retry via
 * `RetryNotificationAction`.
 */
#[Title('Notification failures')]
#[Layout('layouts.app')]
final class Failures extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    /**
     * @var array<int, int>
     */
    public array $selected = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.notification.view');
    }

    public function retry(int $notificationId): void
    {
        try {
            app(RetryNotificationAction::class)->execute(new RetryNotificationData($notificationId));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Retried.'));
    }

    public function retrySelected(): void
    {
        $retried = 0;

        foreach ($this->selected as $notificationId) {
            try {
                app(RetryNotificationAction::class)->execute(new RetryNotificationData($notificationId));
                $retried++;
            } catch (DomainException) {
                // A notification that's no longer failed (already retried
                // successfully by a prior row in this same batch, e.g.)
                // is skipped rather than aborting the whole batch.
            }
        }

        $this->selected = [];
        $this->toast(__(':count notification(s) retried.', ['count' => $retried]));
    }

    public function render(): View
    {
        $query = Notification::query()->where('school_id', $this->school->id)->whereIn('status', ['failed', 'bounced']);

        return view('core::notifications.failures', [
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
            'channel' => ['label' => __('Channel'), 'sortable' => true],
            'recipient_address' => ['label' => __('Recipient'), 'searchable' => true],
            'error_code' => ['label' => __('Error'), 'searchable' => true],
            'attempt_count' => ['label' => __('Attempts'), 'sortable' => true],
            'created_at' => ['label' => __('Created'), 'sortable' => true],
        ];
    }
}
