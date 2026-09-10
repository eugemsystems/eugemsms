<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Imports;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Imports\GenerateImportTemplateData;
use Modules\Core\Domain\Exceptions\UnregisteredImporterException;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Domain\Support\Imports\CsvWriter;

/**
 * ACT-GenerateImportTemplate (Book A CORE-11 §5 `Core\Import\Template`).
 * A CSV with a header row and one worked example row — a "notes"
 * column per field belongs on the Mapper screen's variable palette,
 * not squeezed into the data file itself.
 */
final class GenerateImportTemplateAction extends Action
{
    public function __construct(
        private readonly CsvWriter $csvWriter,
    ) {}

    public function execute(GenerateImportTemplateData $data): string
    {
        if (! ImporterRegistry::has($data->definitionKey)) {
            throw new UnregisteredImporterException("Import definition [{$data->definitionKey}] is not registered.");
        }

        $importer = ImporterRegistry::resolve($data->definitionKey);
        $columns = $importer->templateColumns();

        $example = [];

        foreach ($columns as $column) {
            $example[$column->header] = $column->example;
        }

        return $this->csvWriter->write([$example]);
    }
}
