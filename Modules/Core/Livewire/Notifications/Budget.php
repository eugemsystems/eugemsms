<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Notifications;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Notifications\SetNotificationBudgetAction;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationBudgetData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationBudget;
use Modules\Core\Models\School;

/**
 * `Core\Notifications\Budget` (Book A CORE-09 §5/BR-CORE-09-007,
 * `core.notification.manage_budget`) — this month's spend by channel
 * against its cap, plus a trend of spend by notification key.
 */
#[Title('Notification budget')]
#[Layout('layouts.app')]
final class Budget extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public bool $showEditModal = false;

    public string $editingChannel = '';

    public string $capMinor = '';

    public string $currency = 'USD';

    public int $warnAtPercent = 80;

    public bool $isHardStop = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.notification.manage_budget');
    }

    public function openEditModal(string $channel): void
    {
        $existing = $this->budgetsForCurrentMonth()->get($channel);

        $this->editingChannel = $channel;
        $this->capMinor = $existing !== null && $existing->cap_minor !== null ? (string) ($existing->cap_minor / 100) : '';
        $this->currency = $existing->currency ?? 'USD';
        $this->warnAtPercent = $existing->warn_at_percent ?? 80;
        $this->isHardStop = $existing->is_hard_stop ?? true;
        $this->showEditModal = true;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'capMinor' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'warnAtPercent' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        app(SetNotificationBudgetAction::class)->execute(new SetNotificationBudgetData(
            schoolId: $this->school->id,
            periodMonth: Carbon::now()->format('Y-m'),
            channel: $this->editingChannel,
            currency: strtoupper($this->currency),
            capMinor: $this->capMinor !== '' ? (int) round(((float) $this->capMinor) * 100) : null,
            warnAtPercent: $this->warnAtPercent,
            isHardStop: $this->isHardStop,
        ));

        $this->showEditModal = false;
        $this->toast(__('Budget updated.'));
    }

    /**
     * @return Collection<string, NotificationBudget>
     */
    private function budgetsForCurrentMonth(): Collection
    {
        return NotificationBudget::query()
            ->where('school_id', $this->school->id)
            ->where('period_month', Carbon::now()->format('Y-m'))
            ->get()
            ->keyBy('channel');
    }

    public function render(): View
    {
        $channels = ['sms', 'whatsapp', 'email', 'push', 'in_app'];
        $budgets = $this->budgetsForCurrentMonth();

        $rows = collect($channels)->map(fn (string $channel) => [
            'channel' => $channel,
            'budget' => $budgets->get($channel),
        ]);

        $spendByKey = Notification::query()
            ->where('school_id', $this->school->id)
            ->whereNotNull('cost_minor')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->selectRaw('notification_key, SUM(cost_minor) as total_minor, COUNT(*) as total_count')
            ->groupBy('notification_key')
            ->orderByDesc('total_minor')
            ->get();

        return view('core::notifications.budget', [
            'rows' => $rows,
            'spendByKey' => $spendByKey,
        ]);
    }
}
