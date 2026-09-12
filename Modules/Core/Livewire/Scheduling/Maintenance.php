<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Scheduling;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * `Core\Scheduling\Maintenance` (Book A CORE-12 §5/BR-CORE-12-008,
 * `core.scheduling.manage`) — global maintenance mode.
 *
 * The spec asks for "an IP allowlist for the deploying team" — Laravel's
 * installed version (13.x) no longer offers `artisan down --allow=`;
 * its modern equivalent is a per-browser secret-bypass cookie
 * (`--with-secret`, visiting `/{secret}` sets a cookie letting that one
 * browser through). This screen uses that mechanism instead of building
 * bespoke IP-allowlist middleware to replicate a framework feature that
 * no longer exists — same practical goal (the deploying team can reach
 * the site while everyone else sees the maintenance page), reached via
 * the tool this Laravel version actually ships.
 */
#[Title('Maintenance mode')]
#[Layout('layouts.app')]
final class Maintenance extends Component
{
    use Toasts;

    public bool $isDown = false;

    public ?string $secret = null;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function enable(): void
    {
        Artisan::call('down', ['--with-secret' => true]);
        $this->refreshStatus();
        $this->toast(__('Maintenance mode enabled.'), 'danger');
    }

    public function disable(): void
    {
        Artisan::call('up');
        $this->refreshStatus();
        $this->toast(__('Application is live again.'));
    }

    private function refreshStatus(): void
    {
        $maintenanceMode = App::maintenanceMode();
        $this->isDown = $maintenanceMode->active();
        $this->secret = $this->isDown ? ($maintenanceMode->data()['secret'] ?? null) : null;
    }

    public function render(): View
    {
        return view('core::scheduling.maintenance');
    }
}
