<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Domain\Actions\Audit\RecordActivityAction;
use Modules\Core\Domain\DataObjects\Audit\RecordActivityData;

/**
 * Book A CORE-08 BR-CORE-08-014. The `Auditable` trait dispatches this
 * rather than calling `RecordActivityAction` inline — general activity
 * logging is queued so it never adds latency to the request that
 * triggered it. Contrast `RecordFinancialAuditEntryAction`, which is
 * never queued: that one commits synchronously, in the same
 * transaction as the financial event it logs.
 */
final class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly RecordActivityData $data,
    ) {}

    public function handle(RecordActivityAction $action): void
    {
        $action->execute($this->data);
    }
}
