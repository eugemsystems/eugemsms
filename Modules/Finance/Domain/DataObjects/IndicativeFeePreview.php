<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

/**
 * Book D ACA-02 §7/BR-ACA-02-016. `amountMinor`/`currency` are both
 * null when no fee structure matches — the same "exception, not a
 * zero charge" reasoning `ComputeBillingRunAction` applies (BR-FIN-02
 * no-match handling), so a family is never shown a misleading $0.
 */
final readonly class IndicativeFeePreview
{
    /**
     * @param  array<string, mixed>  $trace
     */
    public function __construct(
        public ?int $amountMinor,
        public ?string $currency,
        public array $trace,
    ) {}
}
