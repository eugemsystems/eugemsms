<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Sessions;

use Modules\Core\Domain\Actions\Sessions\VerifyRolloverInvariantAction;
use Modules\Core\Domain\Contracts\Sessions\RolloverHandler;
use Modules\Core\Domain\DataObjects\Sessions\HandlerResult;
use Modules\Core\Domain\DataObjects\Sessions\RolloverContext;
use Modules\Core\Domain\DataObjects\Sessions\ValidationResult;
use Modules\Core\Models\Term;

/**
 * Book A CORE-03 §5, order 910 — BR-CORE-03-019, the roll-over's own
 * safety net: if every carry-forward handler behaved correctly, opening
 * balances now exactly equal the closing balances the pre-close snapshot
 * captured. A mismatch here means an earlier handler carried the wrong
 * figure, and this handler being blocking is what stops the roll-over
 * from completing on top of that silently.
 */
final class VerifyInvariantHandler implements RolloverHandler
{
    public function __construct(
        private readonly VerifyRolloverInvariantAction $verifyInvariant,
    ) {}

    public function moduleCode(): string
    {
        return 'CORE-03';
    }

    public function sortOrder(): int
    {
        return 910;
    }

    public function isBlocking(): bool
    {
        return true;
    }

    public function validate(Term $from, Term $to): ValidationResult
    {
        return ValidationResult::pass();
    }

    public function execute(Term $from, Term $to, RolloverContext $context): HandlerResult
    {
        $result = $this->verifyInvariant->execute($from, $to);

        $context->put('invariant_result', $result);

        return new HandlerResult(
            $result->holds,
            $result->holds ? 'Roll-over invariant holds.' : 'Roll-over invariant violated.',
            ['per_currency' => $result->perCurrency],
        );
    }

    public function rollback(Term $from, Term $to, RolloverContext $context): void
    {
        // Read-only check — nothing to undo.
    }
}
