<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class GenerateImportTemplateData
{
    public function __construct(
        public string $definitionKey,
    ) {}
}
