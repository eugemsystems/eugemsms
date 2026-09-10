<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 PPL-05 §5/BR-PPL-05-023 (AC-PPL-05-011). ITF16 must
 * reconcile to the twelve monthly P2 returns for the tax year — a
 * mismatch blocks preparation outright rather than producing a wrong
 * annual reconciliation.
 */
final class Itf16ReconciliationException extends DomainException
{
    public static function mismatch(int $schoolId, int $taxYear, int $payslipTotalMinor, int $p2TotalMinor): self
    {
        $diff = $payslipTotalMinor - $p2TotalMinor;

        return new self(
            "ITF16 for tax year {$taxYear} does not reconcile: payslip PAYE totals {$payslipTotalMinor} minor units against {$p2TotalMinor} minor units across the twelve monthly P2 returns (difference of {$diff}).",
            ['school_id' => $schoolId, 'tax_year' => $taxYear, 'payslip_total_minor' => $payslipTotalMinor, 'p2_total_minor' => $p2TotalMinor, 'difference_minor' => $diff],
        );
    }

    public static function incompleteP2Returns(int $schoolId, int $taxYear, int $monthsFound): self
    {
        return new self(
            "ITF16 for tax year {$taxYear} cannot be prepared: only {$monthsFound} of 12 monthly P2 returns exist.",
            ['school_id' => $schoolId, 'tax_year' => $taxYear, 'months_found' => $monthsFound],
        );
    }

    public function errorCode(): string
    {
        return 'ITF16_RECONCILIATION_EXCEPTION';
    }
}
