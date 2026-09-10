<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * Internal to `ExecuteRolloverAction` — unwinds the handler loop inside
 * the DB transaction so `DB::transaction()` rolls everything back
 * (BR-CORE-03-017), then is caught outside it to record the failure on
 * `period_rollovers` in a fresh write the rollback cannot touch. Never
 * escapes the action; not part of the platform's `SerpException`
 * hierarchy since it never reaches an HTTP boundary.
 */
final class RolloverHandlerFailedException extends DomainException
{
    /**
     * @param  array<int, array<string, mixed>>  $stepLog
     */
    public function __construct(
        string $message,
        public readonly string $failingHandler,
        public readonly array $stepLog,
    ) {
        parent::__construct($message, ['failing_handler' => $failingHandler]);
    }

    public function errorCode(): string
    {
        return 'ROLLOVER_HANDLER_FAILED';
    }
}
