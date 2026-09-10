<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

/**
 * Book H3 PPL-05 §4's GL posting layout, bundled into one value
 * object rather than fifteen separate DTO parameters. Every account
 * is passed explicitly by the caller — matching this codebase's
 * established "no system-account auto-provisioning" convention for
 * financial postings (see Finance's own receipt/invoice Actions).
 *
 * Simplified against the spec: "Salaries & Wages (by cost centre)"
 * posts as one undivided debit line in this pass — a per-cost-centre
 * split is deferred.
 */
final readonly class PayrollGlAccounts
{
    public function __construct(
        public int $salariesExpenseAccountId,
        public int $employerNssaExpenseAccountId,
        public int $employerApwcsExpenseAccountId,
        public int $zimdefExpenseAccountId,
        public int $employerNecExpenseAccountId,
        public int $netSalariesPayableAccountId,
        public int $payePayableAccountId,
        public int $aidsLevyPayableAccountId,
        public int $nssaPayableAccountId,
        public int $apwcsPayableAccountId,
        public int $zimdefPayableAccountId,
        public int $necPayableAccountId,
        public int $staffLoansReceivableAccountId,
        public int $feeDebtorsAccountId,
        public int $thirdPartyPayablesAccountId,
    ) {}
}
