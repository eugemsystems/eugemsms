<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ProcessWalletSaleData
{
    /**
     * @param  array<int, array{product_id: int, quantity: float}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $spendPointId,
        public string $paymentMethod,
        public array $lines,
        public int $operatorId,
        public ?int $studentId = null,
        public ?string $identificationMethod = null,
        public ?int $tillSessionId = null,
        public ?CarbonInterface $soldAt = null,
        public string $deviceSource = 'pos',
        public ?string $offlineReference = null,
    ) {}
}
