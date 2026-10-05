<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Broadcasts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\ComposeBroadcastAnnouncementAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\DataObjects\ComposeBroadcastAnnouncementData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\BroadcastAnnouncement;

/**
 * `Saas\Broadcasts\Compose` (Book J SAA-02 §4, vendor console). Targeting is
 * explicit (BR-SAA-02-006): the operator chooses "every tenant" or names the
 * tenants, and an empty selection is an error, never silently "all".
 */
#[Title('Broadcasts')]
#[Layout('saas::layouts.vendor')]
final class Compose extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $title = '';

    public string $body = '';

    public string $severity = 'info';

    public string $audience = 'selected';

    /** @var array<int, int> */
    public array $tenantIds = [];

    public string $startsAt = '';

    public string $endsAt = '';

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function post(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'severity' => ['required', 'in:info,maintenance,incident'],
            'audience' => ['required', 'in:all,selected'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
        ]);

        try {
            $announcement = app(ComposeBroadcastAnnouncementAction::class)->execute(new ComposeBroadcastAnnouncementData(
                title: $this->title, body: $this->body, severity: $this->severity, postedBy: $operator->id,
                targetTenantIds: $this->audience === 'all' ? null : array_map('intval', $this->tenantIds),
                startsAt: $this->startsAt === '' ? null : Carbon::parse($this->startsAt),
                endsAt: $this->endsAt === '' ? null : Carbon::parse($this->endsAt),
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('audience', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'broadcast.posted', "Broadcast “{$announcement->title}” posted", null, ['announcement_id' => $announcement->id, 'targets' => $announcement->target_tenant_ids]);

        $this->reset('title', 'body', 'tenantIds', 'startsAt', 'endsAt');
        $this->toast(__('Announcement posted.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        return view('saas::vendor.broadcasts', [
            'announcements' => BroadcastAnnouncement::query()->orderByDesc('id')->limit(50)->get(),
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }
}
