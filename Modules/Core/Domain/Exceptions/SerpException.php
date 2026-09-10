<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Root of the platform exception hierarchy (Book A Part 1.6). Every
 * exception carries a stable machine `code` used verbatim in the API
 * error envelope (Volume 1 §9.2), and a human message safe to display
 * to the caller.
 */
abstract class SerpException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        protected readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    abstract public function errorCode(): string;

    abstract public function httpStatus(): int;

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * @return array<string, mixed>
     */
    public function toErrorEnvelope(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode(),
                'message' => $this->getMessage(),
                'details' => $this->details(),
            ],
        ];
    }
}
