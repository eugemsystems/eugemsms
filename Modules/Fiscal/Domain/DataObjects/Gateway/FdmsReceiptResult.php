<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects\Gateway;

final readonly class FdmsReceiptResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $accepted,
        public array $rawResponse,
        public ?string $fdmsReceiptId = null,
        public ?string $verificationCode = null,
        public ?string $qrUrl = null,
        public ?string $receiptHash = null,
        public ?string $receiptSignature = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}
}
