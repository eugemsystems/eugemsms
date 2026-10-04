<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class UpdatePaymentGatewayData
{
    /**
     * @param  array<int, string>  $supportedMethods
     * @param  array<int, string>  $supportedCurrencies
     * @param  array<string, mixed>|null  $feeModel
     */
    public function __construct(
        public int $gatewayId,
        public string $name,
        public array $supportedMethods,
        public array $supportedCurrencies,
        public int $settlementAccountId,
        public int $feeAccountId,
        public ?array $feeModel,
        public bool $isDefault,
        public bool $isSandbox,
        public bool $isActive,
        public int $priority,
        public ?string $credentials = null,
    ) {}
}
