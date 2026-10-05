<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Integrations\Usage;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\ApiUsageLog;

/**
 * `Intelligence\Integrations\Usage\Dashboard` (Book J INT-04 §5,
 * `integration.view`). The school's own clients only (BR-INT-04-011):
 * request volume, error rate and latency over a chosen window, read
 * from `api_usage_log` and scoped to this school.
 */
#[Title('API usage')]
#[Layout('layouts.app')]
final class Dashboard extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public int $days = 7;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('integration.view');
    }

    public function render(): View
    {
        $days = in_array($this->days, [1, 7, 30], true) ? $this->days : 7;

        $rows = ApiUsageLog::where('school_id', $this->school->id)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->select('client_id', DB::raw('count(*) as requests'), DB::raw('sum(case when status_code >= 400 then 1 else 0 end) as errors'), DB::raw('avg(duration_ms) as avg_ms'))
            ->groupBy('client_id')->orderByDesc('requests')->toBase()->get();

        return view('intelligence::integrations.usage', [
            'rows' => $rows,
            'clientNames' => ApiClient::where('school_id', $this->school->id)->whereIn('id', $rows->pluck('client_id'))->pluck('name', 'id'),
            'selectedDays' => $days,
        ]);
    }
}
