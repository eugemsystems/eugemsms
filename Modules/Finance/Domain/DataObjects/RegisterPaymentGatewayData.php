<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RegisterPaymentGatewayData
{
    /**
     * @param  array<int, string>  $supportedMethods
     * @param  array<int, string>  $supportedCurrencies
     * @param  array<string, mixed>|null  $feeModel
     */
    public function __construct(
        public int $schoolId,
        public string $driver,
        public string $name,
        public string $credentials,
        public array $supportedMethods,
        public array $supportedCurrencies,
        public int $settlementAccountId,
        public int $feeAccountId,
        public ?array $feeModel = null,
        public bool $isDefault = false,
        public bool $isSandbox = true,
        public bool $isActive = false,
    ) {}
}
