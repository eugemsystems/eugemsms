<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\RecordFocusEventData;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-RecordFocusEvent (Book K ACA-09 §4/BR-ACA-09-006/AC-ACA-09-005).
 * Purely a log-and-flag mechanism — never a penalty, never an
 * automatic disqualification, exactly as `ACA-08`'s similarity
 * detection is advisory only.
 */
final class RecordFocusEventAction extends Action
{
    public function execute(RecordFocusEventData $data): CbtCandidateAttempt
    {
        $attempt = CbtCandidateAttempt::findOrFail($data->attemptId);

        if (! in_array($data->eventType, ['tab_switch', 'window_blur', 'window_focus', 'fullscreen_exit'], true)) {
            throw new InvalidArgumentException("[{$data->eventType}] is not a focus event.");
        }

        if (! in_array($attempt->status, ['in_progress', 'flagged'], true) || ! $attempt->test->browser_focus_monitoring) {
            return $attempt;
        }

        return $this->transaction(function () use ($attempt, $data): CbtCandidateAttempt {
            $events = $attempt->focus_events ?? [];
            $events[] = ['type' => $data->eventType, 'at' => Carbon::now()->toIso8601String()];

            $tabSwitchCount = $data->eventType === 'tab_switch' ? $attempt->tab_switch_count + 1 : $attempt->tab_switch_count;
            $maxAllowed = $attempt->test->max_tab_switches ?? (int) app(SettingResolver::class)->get('cbt.default_max_tab_switches', new ScopeChain(schoolId: $attempt->school_id));

            $attempt->update([
                'focus_events' => $events,
                'tab_switch_count' => $tabSwitchCount,
                'status' => in_array($attempt->status, ['in_progress', 'flagged'], true) && $tabSwitchCount > $maxAllowed
                    ? 'flagged'
                    : $attempt->status,
            ]);

            return $attempt->fresh();
        });
    }
}
