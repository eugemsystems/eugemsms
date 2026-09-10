<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Sessions;

use Modules\Core\Domain\Contracts\Sessions\RolloverHandler;
use Modules\Core\Domain\DataObjects\Sessions\HandlerResult;
use Modules\Core\Domain\DataObjects\Sessions\RolloverContext;
use Modules\Core\Domain\DataObjects\Sessions\ValidationResult;
use Modules\Core\Models\Term;

/**
 * Book A CORE-03 §5, order 920 — non-blocking (a missing report never
 * holds up a roll-over that otherwise succeeded). Real document
 * generation belongs to CORE-06 (Numbering, Templates & Document
 * Generation, not built yet); `period_rollovers.report_document_id`
 * stays null until it is.
 */
final class GenerateRolloverReportHandler implements RolloverHandler
{
    public function moduleCode(): string
    {
        return 'CORE-03';
    }

    public function sortOrder(): int
    {
        return 920;
    }

    public function isBlocking(): bool
    {
        return false;
    }

    public function validate(Term $from, Term $to): ValidationResult
    {
        return ValidationResult::pass();
    }

    public function execute(Term $from, Term $to, RolloverContext $context): HandlerResult
    {
        return new HandlerResult(
            false,
            'Roll-over report generation is not available yet — ships with CORE-06.',
        );
    }

    public function rollback(Term $from, Term $to, RolloverContext $context): void
    {
        // Nothing was executed.
    }
}
