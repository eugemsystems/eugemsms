<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Scheduling;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Scheduling\RunHealthChecksAction;
use Modules\Core\Domain\DataObjects\Scheduling\RunHealthChecksData;
use Modules\Core\Domain\Registry\HealthCheckRegistry;
use Modules\Core\Models\SystemHealthCheck;

/**
 * `Core\Scheduling\Health` (Book A CORE-12 §3, `core.scheduling.view`) —
 * the latest reading for every available registered health check, with
 * a "Run now" button (the nightly/continuous schedule that would run
 * these automatically is a later wave, same as every other CORE
 * background job in Book A — see `Audit\Integrity`'s identical note).
 * A check whose owning module doesn't exist yet (`isAvailable() ===
 * false`, e.g. fiscalisation queue depth) is omitted entirely rather
 * than shown as a permanent failure, the same choice `Audit\Integrity`
 * makes for `IntegrityCheckRegistry`.
 */
#[Title('System health')]
#[Layout('layouts.app')]
final class Health extends Component
{
    use Toasts;

    public function runChecks(): void
    {
        $results = app(RunHealthChecksAction::class)->execute(new RunHealthChecksData);

        $unhealthy = collect($results)->filter(fn (SystemHealthCheck $check): bool => $check->status === 'unhealthy')->count();

        $this->toast(
            $unhealthy === 0
                ? __(':count check(s) run — all healthy or degraded within tolerance.', ['count' => count($results)])
                : __(':count of :total check(s) are unhealthy.', ['count' => $unhealthy, 'total' => count($results)]),
            $unhealthy === 0 ? 'success' : 'danger',
        );
    }

    public function render(): View
    {
        $latest = SystemHealthCheck::query()->get()->keyBy('check_key');

        $checks = collect(HealthCheckRegistry::all())
            ->filter(fn ($check): bool => $check->isAvailable())
            ->map(fn ($check, string $key) => [
                'key' => $key,
                'latest' => $latest->get($key),
            ])
            ->values();

        return view('core::scheduling.health', ['checks' => $checks]);
    }
}
