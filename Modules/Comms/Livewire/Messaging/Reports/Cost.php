<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Messaging\Reports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\MessageSegment;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;

/**
 * `Comms\Reports\Cost` (Book I COM-01 §6, `comms.report.view`). Spend
 * is `notifications.cost_minor` — the same figure budget enforcement
 * and `ReconcileGatewayCostAction` use, never a second total. The
 * segment-waste count is the spec's "could have been 1 segment but hit
 * 2" report, via `MessageSegment::isWasted()`'s own definition.
 */
#[Title('Messaging cost & segmentation')]
#[Layout('layouts.app')]
final class Cost extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $month = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('comms.report.view');

        $this->month = now()->format('Y-m');
    }

    public function render(): View
    {
        $start = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1
            ? Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfMonth()
            : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $spendByChannel = Notification::where('school_id', $this->school->id)
            ->whereNotNull('cost_minor')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('channel, cost_currency, count(*) as messages, sum(cost_minor) as total_minor')
            ->groupBy('channel', 'cost_currency')
            ->orderBy('channel')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'channel' => $row->channel,
                'messages' => (int) $row->messages,
                'total' => $this->formatMinor((int) $row->total_minor, $row->cost_currency),
            ]);

        $encodingBreakdown = MessageSegment::where('school_id', $this->school->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('encoding, count(*) as messages, sum(segment_count) as segments')
            ->groupBy('encoding')
            ->get();

        $wastedMessages = MessageSegment::where('school_id', $this->school->id)
            ->whereBetween('created_at', [$start, $end])
            ->where('encoding', 'ucs2')->where('segment_count', '>', 1)->where('character_count', '<=', 160)
            ->count();

        return view('comms::reports.cost', [
            'spendByChannel' => $spendByChannel,
            'encodingBreakdown' => $encodingBreakdown,
            'wastedMessages' => $wastedMessages,
        ]);
    }

    private function formatMinor(int $minor, ?string $currency): string
    {
        return Money::of($minor, Currency::tryFrom((string) $currency) ?? Currency::from($this->school->base_currency))->format();
    }
}
