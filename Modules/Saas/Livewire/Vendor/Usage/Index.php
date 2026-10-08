<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Usage;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Scopes\SchoolScope;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ApiUsageLog;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;

/**
 * `Vendor\Usage\Index` (Book J INT-04 §5/BR-INT-04-011, vendor console).
 * The vendor's own aggregate view of third-party API usage — platform
 * totals plus a per-tenant breakdown, the vendor-side half of
 * `Integrations\Usage\Dashboard`'s own school-scoped view, which this
 * does not replace: a school still only ever sees its own clients.
 * This screen queries across every tenant deliberately, the one place
 * in the codebase that is meant to.
 */
#[Title('API usage (platform)')]
#[Layout('saas::layouts.vendor')]
final class Index extends Component
{
    use AuthorizesVendorConsole;

    public int $days = 7;

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $days = in_array($this->days, [1, 7, 30], true) ? $this->days : 7;
        $since = now()->subDays($days);

        $totals = ApiUsageLog::withoutGlobalScope(SchoolScope::class)
            ->where('occurred_at', '>=', $since)
            ->selectRaw('count(*) as requests, sum(case when status_code >= 400 then 1 else 0 end) as errors, avg(duration_ms) as avg_ms')
            ->toBase()->first();

        $byTenant = ApiUsageLog::withoutGlobalScope(SchoolScope::class)
            ->where('occurred_at', '>=', $since)
            ->select('school_id', DB::raw('count(*) as requests'), DB::raw('sum(case when status_code >= 400 then 1 else 0 end) as errors'), DB::raw('avg(duration_ms) as avg_ms'))
            ->groupBy('school_id')->orderByDesc('requests')->toBase()->get();

        $schools = School::query()->withoutGlobalScope(SchoolScope::class)->with('tenant')->whereIn('id', $byTenant->pluck('school_id'))->get()->keyBy('id');

        return view('saas::vendor.usage', [
            'totals' => $totals,
            'byTenant' => $byTenant,
            'schools' => $schools,
            'selectedDays' => $days,
        ]);
    }
}
