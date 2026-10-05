<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordActivityAction;
use Modules\Core\Domain\DataObjects\Audit\RecordActivityData;

/**
 * ACT-RecordVendorConsoleAction (Book J SAA-02 §3/BR-SAA-02-007). Every
 * vendor-console action against a specific tenant leaves an activity-log
 * row (`log_name = vendor_console`) naming the operator, tenant, event and
 * the request's IP — the console with the widest blast radius in the
 * platform is the one that must never act unseen. Written synchronously,
 * inside the caller's own transaction where there is one.
 */
final class RecordVendorConsoleActionAction extends Action
{
    public function __construct(
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function execute(User $operator, string $event, string $description, ?int $tenantId = null, array $properties = []): void
    {
        $this->recordActivity->execute(new RecordActivityData(
            logName: 'vendor_console',
            description: $description,
            subjectType: $tenantId === null ? null : 'tenant',
            subjectId: $tenantId,
            causerType: User::class,
            causerId: $operator->id,
            event: $event,
            properties: ['attributes' => $properties],
            ip: request()->ip(),
            userAgent: request()->userAgent(),
        ));
    }
}
