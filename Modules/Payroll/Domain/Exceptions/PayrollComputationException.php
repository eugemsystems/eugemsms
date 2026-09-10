<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 PPL-05 §4/BR-PPL-05-012/015 (AC-PPL-05-006). Raised for one
 * staff member during `ComputePayrollRunAction`'s loop — caught there
 * and recorded on the run's `exception_report` rather than aborting
 * the whole run. No payslip is created for the staff member named;
 * this is "an exception, never a posting" (BR-PPL-05-012).
 */
class PayrollComputationException extends DomainException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly int $staffId,
        public readonly string $reason,
        array $context = [],
    ) {
        parent::__construct($message, ['staff_id' => $staffId, 'reason' => $reason, ...$context]);
    }

    public static function missingPayStructure(int $staffId): self
    {
        return new self("Staff member [{$staffId}] has no active pay structure for this pay date.", $staffId, 'missing_pay_structure');
    }

    public static function staffExited(int $staffId): self
    {
        return new self("Staff member [{$staffId}] exited before this run's pay date.", $staffId, 'staff_exited');
    }

    public static function missingBankDetails(int $staffId): self
    {
        return new self("Staff member [{$staffId}] has no bank account number on file.", $staffId, 'missing_bank_details');
    }

    public static function missingStatutoryIdentifiers(int $staffId): self
    {
        return new self("Staff member [{$staffId}] is missing a ZIMRA BP or NSSA number.", $staffId, 'missing_statutory_identifiers');
    }

    public static function negativeNetPay(int $staffId, int $netMinor): self
    {
        return new self("Staff member [{$staffId}] would have a negative net pay of {$netMinor} minor units.", $staffId, 'negative_net_pay', ['net_minor' => $netMinor]);
    }

    public static function contractExpired(int $staffId, int $contractId): self
    {
        return new self("Staff member [{$staffId}]'s linked contract [{$contractId}] expired before this run's pay date.", $staffId, 'contract_expired', ['contract_id' => $contractId]);
    }

    public function errorCode(): string
    {
        return 'PAYROLL_COMPUTATION_EXCEPTION';
    }
}
