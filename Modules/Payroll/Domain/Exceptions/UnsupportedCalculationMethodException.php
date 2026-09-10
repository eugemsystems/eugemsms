<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 PPL-05 §2. This pass supports `fixed` and
 * `percentage_of_basic` pay components only — `percentage_of_gross`,
 * `formula`, `hourly`, and `per_unit` are real spec'd calculation
 * methods left for a later pass, matching this codebase's
 * established "throw for an unsupported-but-spec'd method rather
 * than silently mis-computing it" convention (see FIN-02's
 * `FeeLineCalculator`).
 */
class UnsupportedCalculationMethodException extends DomainException
{
    public static function forMethod(string $method, string $componentCode): self
    {
        return new self(
            "Calculation method [{$method}] on pay component [{$componentCode}] is not yet supported.",
            ['method' => $method, 'component_code' => $componentCode],
        );
    }

    public function errorCode(): string
    {
        return 'UNSUPPORTED_CALCULATION_METHOD';
    }
}
