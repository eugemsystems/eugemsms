<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * `prepaidAssetAccountId`/`academicYearId`/`termId` are only needed
 * when the meter's own `UtilityAccount.billing_mode` is `prepaid` —
 * that's when this action recognises consumption expense for real
 * (Dr Electricity Expense / Cr Prepaid Electricity, BR-OPS-04-004). A
 * postpaid meter's invoice arrives through `FIN-08` separately, the
 * same documented boundary
 * `Modules\Transport\Domain\Actions\RecordContractorCostAction`
 * already uses for a cost this module can't complete alone.
 */
final readonly class RecordMeterReadingData
{
    public function __construct(
        public int $schoolId,
        public int $meterId,
        public CarbonInterface $readOn,
        public float $reading,
        public string $readingMethod,
        public int $readByUserId,
        public ?int $photoFileId = null,
        public ?int $academicYearId = null,
        public ?int $termId = null,
        public ?int $prepaidAssetAccountId = null,
        public ?int $postedByUserId = null,
    ) {}
}
