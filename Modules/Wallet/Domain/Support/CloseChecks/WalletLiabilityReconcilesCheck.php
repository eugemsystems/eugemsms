<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Support\CloseChecks;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Wallet\Domain\Actions\ReconcileWalletLiabilityAction;

/**
 * Book H3 FIN-12 §4, "Wallet liability reconciles" — reuses the real
 * `ReconcileWalletLiabilityAction` (FIN-14, this same book) directly.
 */
final class WalletLiabilityReconcilesCheck implements CloseChecklistItem
{
    public function __construct(
        private readonly ReconcileWalletLiabilityAction $reconcile,
    ) {}

    public function code(): string
    {
        return 'fin14_wallet_liability_reconciles';
    }

    public function label(): string
    {
        return 'Wallet liability reconciles';
    }

    public function appliesTo(): PeriodType
    {
        return PeriodType::Financial;
    }

    public function isBlocking(): bool
    {
        return true;
    }

    public function check(Term $term): ChecklistItemResult
    {
        $result = $this->reconcile->execute($term->school_id);
        $passed = $result['variance_minor'] === 0;

        return new ChecklistItemResult(
            code: $this->code(),
            label: $this->label(),
            passed: $passed,
            blocking: $this->isBlocking(),
            message: $passed ? 'Wallet balances reconcile to the liability account.' : "Variance of {$result['variance_minor']} minor units.",
            details: $result,
        );
    }
}
