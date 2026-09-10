<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Sessions;

use Modules\Core\Domain\DataObjects\Sessions\HandlerResult;
use Modules\Core\Domain\DataObjects\Sessions\RolloverContext;
use Modules\Core\Domain\DataObjects\Sessions\ValidationResult;
use Modules\Core\Models\Term;

/**
 * Book A CORE-03 §5. Every module that owns period-bound data registers
 * one of these into `RolloverHandlerRegistry`. CORE-03 orchestrates; it
 * knows nothing about fees or marks — see the registered handler order
 * table in the spec (FIN-01's trial-balance assertion first, CORE-03's
 * own snapshot/invariant handlers bracketing the module handlers,
 * non-blocking clone/promote handlers last).
 */
interface RolloverHandler
{
    public function moduleCode(): string;

    public function sortOrder(): int;

    /**
     * Blocking handlers that fail abort and roll back the entire
     * transaction (BR-CORE-03-017/018). Non-blocking failures are
     * recorded in the exception report and the roll-over still completes
     * (BR-CORE-03-023).
     */
    public function isBlocking(): bool;

    /** Read-only. Runs before anything is written (BR-CORE-03-018). */
    public function validate(Term $from, Term $to): ValidationResult;

    /** Executes inside the roll-over transaction. */
    public function execute(Term $from, Term $to, RolloverContext $context): HandlerResult;

    /** Must undo execute() exactly. Called on failure of a later handler. */
    public function rollback(Term $from, Term $to, RolloverContext $context): void;
}
