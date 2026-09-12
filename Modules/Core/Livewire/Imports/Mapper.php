<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Imports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\Actions\Imports\CreateImportBatchAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\DataObjects\Imports\CreateImportBatchData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Domain\Support\Imports\CsvReader;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\ImportDefinition;
use Modules\Core\Models\School;
use RuntimeException;

/**
 * `Core\Import\Mapper` (Book A CORE-11 §5) — upload a CSV, map its
 * headers onto the importer's declared template columns, choose a
 * duplicate strategy, and create the batch (still in its `mapping`
 * status; `Core\Import\Batch` runs validation next).
 *
 * "Auto-maps by header similarity" (spec §5) is implemented as an exact,
 * case/whitespace/punctuation-insensitive header match — not fuzzy
 * (Levenshtein) matching, and "saved mapping profiles" isn't built at
 * all — both are genuine nice-to-haves the spec names but no
 * `MappingProfile` model/registry exists to back the latter with, and
 * exact-normalised matching already covers the common case (a template
 * downloaded from this same screen re-uploaded unchanged).
 */
#[Title('Map import file')]
#[Layout('layouts.app')]
final class Mapper extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use WithFileUploads;

    public string $definitionKey;

    public ?TemporaryUploadedFile $file = null;

    /** @var array<int, string> */
    public array $sourceHeaders = [];

    /**
     * Indexed by position in `templateColumns()`, not by header text —
     * a `wire:model="columnMapping.$header"` path would silently
     * misbehave for any header containing a literal `.`, which Livewire
     * reads as a nested-property separator.
     *
     * @var array<int, string>
     */
    public array $columnMapping = [];

    public string $duplicateStrategy = 'skip';

    public bool $dryRun = false;

    public function mount(School $school, string $definitionKey): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);

        $definition = ImportDefinition::where('key', $definitionKey)->firstOrFail();
        $this->definitionKey = $definitionKey;
        $this->authorizePermission($definition->required_permission);

        $unmet = array_values(array_diff(
            $definition->depends_on ?? [],
            ImportBatch::where('school_id', $school->id)->where('status', 'completed')->pluck('definition_key')->all(),
        ));

        abort_unless($unmet === [], 409, 'This import has unmet dependencies: '.implode(', ', $unmet));

        $importer = ImporterRegistry::resolve($definitionKey);
        $this->columnMapping = array_fill(0, count($importer->templateColumns()), '');
    }

    public function updatedFile(): void
    {
        if ($this->file === null) {
            return;
        }

        $rows = app(CsvReader::class)->parse($this->readFile($this->file));

        // `CsvReader::parse()` consumes the header row to key every data
        // row by it and returns no separate header accessor — so header
        // names are read back off the first data row's own keys. An
        // uploaded file with a header row but zero data rows has no way
        // to recover its headers here; the user re-adds a data row.
        $this->sourceHeaders = $rows === [] ? [] : array_keys(reset($rows));

        $this->autoMap();
    }

    private function readFile(TemporaryUploadedFile $file): string
    {
        $contents = $file->get();

        if ($contents === false) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        return $contents;
    }

    private function autoMap(): void
    {
        $normalise = fn (string $value): string => strtolower(preg_replace('/[^a-z0-9]+/i', '', $value) ?? '');
        $importer = ImporterRegistry::resolve($this->definitionKey);

        foreach ($importer->templateColumns() as $index => $column) {
            foreach ($this->sourceHeaders as $sourceHeader) {
                if ($normalise($column->header) === $normalise($sourceHeader)) {
                    $this->columnMapping[$index] = $sourceHeader;

                    break;
                }
            }
        }
    }

    public function createBatch(): void
    {
        $this->validate([
            'file' => ['required', 'file'],
            'duplicateStrategy' => ['required', 'in:skip,update,create_anyway'],
        ]);

        $definition = ImportDefinition::where('key', $this->definitionKey)->firstOrFail();
        $this->authorizePermission($definition->required_permission);

        $importer = ImporterRegistry::resolve($this->definitionKey);
        $templateColumns = $importer->templateColumns();

        $missingRequired = collect($templateColumns)
            ->filter(fn ($column, int $index) => $column->required && ($this->columnMapping[$index] ?? '') === '')
            ->pluck('header');

        if ($missingRequired->isNotEmpty()) {
            $this->addError('columnMapping', __('Map every required column: :columns', ['columns' => $missingRequired->implode(', ')]));

            return;
        }

        $mapping = [];

        foreach ($templateColumns as $index => $column) {
            if (($this->columnMapping[$index] ?? '') !== '') {
                $mapping[$column->header] = $this->columnMapping[$index];
            }
        }

        try {
            $sourceFile = app(UploadFileAction::class)->execute(new UploadFileData(
                schoolId: $this->school->id,
                category: 'import_source_file',
                contents: $this->readFile($this->file),
                originalName: $this->file->getClientOriginalName(),
                uploadedByUserId: (int) Auth::id(),
            ));

            $batch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData(
                schoolId: $this->school->id,
                definitionKey: $this->definitionKey,
                sourceFileId: $sourceFile->id,
                columnMapping: $mapping,
                importedByUserId: (int) Auth::id(),
                duplicateStrategy: $this->duplicateStrategy,
                dryRun: $this->dryRun,
                academicYearId: SessionContext::isSet() ? SessionContext::yearId() : null,
                termId: SessionContext::isSet() ? SessionContext::termId() : null,
            ));
        } catch (DomainException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->redirectRoute('imports.batches.show', ['school' => $this->school, 'batch' => $batch], navigate: true);
    }

    public function render(): View
    {
        $definition = ImportDefinition::where('key', $this->definitionKey)->firstOrFail();
        $importer = ImporterRegistry::resolve($this->definitionKey);

        return view('core::imports.mapper', [
            'definition' => $definition,
            'templateColumns' => $importer->templateColumns(),
        ]);
    }
}
