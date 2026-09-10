<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Exceptions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Payroll\Models\StatutoryConfiguration;

/**
 * Book H3 PPL-05 §0.1/BR-PPL-05-002 ⭐ (AC-PPL-05-001). A payroll run
 * is blocked while any applicable configuration is unconfirmed —
 * also thrown when no configuration exists at all for the requested
 * type/date/currency, since an absent rate is no more usable than an
 * unconfirmed one.
 */
class UnconfirmedStatutoryConfigException extends DomainException
{
    public static function forConfig(StatutoryConfiguration $config): self
    {
        return new self(
            "Statutory configuration [{$config->config_type}] (effective {$config->effective_from->toDateString()}) requires confirmation before payroll can run.",
            ['config_id' => $config->id, 'config_type' => $config->config_type],
        );
    }

    public static function notFound(string $configType, int $schoolId, CarbonInterface $payDate): self
    {
        return new self(
            "No active statutory configuration [{$configType}] is in force for school [{$schoolId}] on [{$payDate->toDateString()}].",
            ['config_type' => $configType, 'school_id' => $schoolId, 'pay_date' => $payDate->toDateString()],
        );
    }

    public function errorCode(): string
    {
        return 'STATUTORY_CONFIG_UNCONFIRMED';
    }
}
