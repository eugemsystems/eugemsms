<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Fiscal\Domain\Events\OfflineQueueBacklog;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * ACT-DrainOfflineFiscalQueue (Book H3 FIN-13 §4/BR-FIN-13-009/010).
 * Retries every `offline_queued` receipt for a school, oldest
 * `global_counter` first (drains "in counter order" — BR-FIN-13-009),
 * capped at `fiscal.submission_retry_attempts`. Fires
 * `OfflineQueueBacklog` when the depth crosses
 * `fiscal.offline_queue_alert_depth`, checked BEFORE draining so a
 * still-large backlog is visible even if this run clears some of it.
 */
final class DrainOfflineFiscalQueueAction extends Action
{
    public function __construct(
        private readonly SubmitFiscalReceiptAction $submitReceipt,
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, FiscalReceipt>
     */
    public function execute(int $schoolId): Collection
    {
        $maxAttempts = (int) $this->settings->get('fiscal.submission_retry_attempts', new ScopeChain(schoolId: $schoolId));
        $alertDepth = (int) $this->settings->get('fiscal.offline_queue_alert_depth', new ScopeChain(schoolId: $schoolId));

        $queued = FiscalReceipt::where('school_id', $schoolId)
            ->where('status', 'offline_queued')
            ->where('attempt_count', '<', $maxAttempts)
            ->orderBy('global_counter')
            ->get();

        if ($queued->count() >= $alertDepth) {
            event(new OfflineQueueBacklog($schoolId, $queued->count()));
        }

        return $queued->map(fn (FiscalReceipt $receipt): FiscalReceipt => $this->submitReceipt->execute($receipt->id));
    }
}
