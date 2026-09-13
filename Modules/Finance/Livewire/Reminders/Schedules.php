<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Reminders;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateReminderScheduleAction;
use Modules\Finance\Domain\Actions\SetReminderScheduleActiveAction;
use Modules\Finance\Domain\DataObjects\CreateReminderScheduleData;
use Modules\Finance\Domain\DataObjects\SetReminderScheduleActiveData;
use Modules\Finance\Models\ReminderSchedule;

/**
 * `Finance\Reminders\Schedules` (Book B FIN-03 §5/BR-FIN-03-015/016,
 * `finance.reminder.manage`) — the chase ladder's own rungs.
 */
#[Title('Reminder schedules')]
#[Layout('layouts.app')]
final class Schedules extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $name = '';

    public int $daysAfterDue = 7;

    public int $minimumBalanceMinor = 0;

    public string $currency = '';

    /**
     * @var array<int, string>
     */
    public array $channels = ['sms'];

    public string $audience = 'fee_responsible';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.reminder.manage');
    }

    public function openCreateModal(): void
    {
        $this->reset(['name', 'daysAfterDue', 'minimumBalanceMinor', 'currency', 'channels', 'audience']);
        $this->daysAfterDue = 7;
        $this->channels = ['sms'];
        $this->audience = 'fee_responsible';
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'daysAfterDue' => ['required', 'integer', 'min:0'],
            'minimumBalanceMinor' => ['required', 'integer', 'min:0'],
            'channels' => ['array', 'min:1'],
            'audience' => ['required', 'in:fee_responsible,all_guardians'],
        ]);

        app(CreateReminderScheduleAction::class)->execute(new CreateReminderScheduleData(
            schoolId: $this->school->id,
            name: $this->name,
            daysAfterDue: $this->daysAfterDue,
            channels: $this->channels,
            templateKey: 'finance.fee_reminder',
            audience: $this->audience,
            minimumBalanceMinor: $this->minimumBalanceMinor,
            currency: $this->currency !== '' ? $this->currency : null,
        ));

        $this->showCreateModal = false;
        $this->toast(__('Reminder schedule created.'));
    }

    public function toggleActive(int $scheduleId, bool $isActive): void
    {
        app(SetReminderScheduleActiveAction::class)->execute(new SetReminderScheduleActiveData($scheduleId, $isActive));
        $this->toast($isActive ? __('Schedule activated.') : __('Schedule deactivated.'));
    }

    public function render(): View
    {
        return view('finance::reminders.schedules', [
            'schedules' => ReminderSchedule::orderBy('days_after_due')->get(),
        ]);
    }
}
