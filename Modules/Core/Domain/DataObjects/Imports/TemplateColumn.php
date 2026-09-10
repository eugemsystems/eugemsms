<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class TemplateColumn
{
    public function __construct(
        public string $header,
        public string $example,
        public bool $required,
        public ?string $notes = null,
    ) {}
}
