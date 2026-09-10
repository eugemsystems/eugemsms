<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Sessions;

use Modules\Core\Domain\Contracts\Sessions\RolloverHandler;
use Modules\Core\Domain\DataObjects\Sessions\HandlerResult;
use Modules\Core\Domain\DataObjects\Sessions\RolloverContext;
use Modules\Core\Domain\DataObjects\Sessions\ValidationResult;
use Modules\Core\Models\Term;

/**
 * A handler named in Book A CORE-03 §5's registration-order table whose
 * owning module isn't built yet. Registered so `RolloverHandlerRegistry`
 * (and the rollover wizard screen, once it exists) can list the full
 * intended pipeline — visibly, honestly not-yet-real — instead of either
 * silently omitting the step or crashing when that module lands out of
 * order. Never actually runs: `ACT-InitiateRollover`/`ACT-ExecuteRollover`
 * skip any handler whose module isn't installed, exactly like
 * `PendingSeedPack` and `SeedZimbabweBaselineAction`.
 */
final readonly class PendingRolloverHandler implements RolloverHandler
{
    public function __construct(
        private string $moduleCode,
        private string $handlerName,
        private int $sortOrder,
        private bool $blocking,
    ) {}

    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isBlocking(): bool
    {
        return $this->blocking;
    }

    public function validate(Term $from, Term $to): ValidationResult
    {
        return ValidationResult::fail(["{$this->handlerName} is not available yet — ships with {$this->moduleCode}."]);
    }

    public function execute(Term $from, Term $to, RolloverContext $context): HandlerResult
    {
        return new HandlerResult(false, "{$this->handlerName} is not available yet — ships with {$this->moduleCode}.");
    }

    public function rollback(Term $from, Term $to, RolloverContext $context): void
    {
        // Nothing was executed — nothing to undo.
    }
}
