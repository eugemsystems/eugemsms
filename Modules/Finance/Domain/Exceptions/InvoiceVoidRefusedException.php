<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-03 §4/BR-FIN-03-005 (AC-FIN-03-002). Voiding is refused
 * while any payment is allocated to the invoice — settle or reallocate
 * first.
 */
class InvoiceVoidRefusedException extends DomainException
{
    public static function paymentAllocated(int $invoiceId, int $paidMinor): self
    {
        return new self(
            "Invoice [{$invoiceId}] has {$paidMinor} minor units allocated against it and cannot be voided — reallocate or credit the payment first.",
            ['invoice_id' => $invoiceId, 'paid_minor' => $paidMinor],
        );
    }

    public function errorCode(): string
    {
        return 'INVOICE_VOID_REFUSED';
    }
}
