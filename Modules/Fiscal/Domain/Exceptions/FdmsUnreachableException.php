<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Exceptions;

use RuntimeException;

/**
 * Book H3 FIN-13 §4 — thrown by a `FiscalGatewayDriver` when FDMS
 * cannot be reached at all (as opposed to reaching FDMS and being
 * rejected, which is a normal `FdmsReceiptResult`, not an exception).
 * Callers catch this specifically to route into the offline queue —
 * BR-FIN-13-001's cardinal rule that this never blocks the receipt
 * itself is enforced by every caller of a `FiscalGatewayDriver`
 * method, never by the driver.
 */
final class FdmsUnreachableException extends RuntimeException {}
