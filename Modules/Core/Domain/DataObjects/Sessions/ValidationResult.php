<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

/**
 * Book A CORE-03 §5 — `RolloverHandler::validate()`'s result. Read-only:
 * validation never writes.
 */
final readonly class ValidationResult
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public bool $passed,
        public array $errors = [],
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    /**
     * @param  array<int, string>  $errors
     */
    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }
}
