<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

/**
 * Book A CORE-03 §5 — `RolloverHandler::execute()`'s result, recorded
 * verbatim into `period_rollovers.step_log`.
 */
final readonly class HandlerResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $success,
        public string $message = '',
        public array $data = [],
    ) {}
}
