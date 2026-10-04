<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Bank;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Imports\CsvReader;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ImportBankStatementAction;
use Modules\Finance\Domain\DataObjects\ImportBankStatementData;
use Modules\Finance\Models\BankAccount;
use Throwable;

/**
 * `Finance\Bank\Import` (Book B FIN-05 §3/§8/BR-FIN-05-011,
 * `finance.bank.reconcile`). `ImportBankStatementAction` takes
 * already-parsed line data (see its own docblock) — CSV parsing and
 * column mapping is this screen's job, reusing `Core\Domain\Support\Imports\CsvReader`
 * (Book A CORE-11) exactly the way `Core\Imports\Mapper` already does,
 * rather than a second hand-rolled CSV parser.
 */
#[Title('Import bank statement')]
#[Layout('layouts.app')]
final class Import extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;
    use WithFileUploads;

    public ?int $bankAccountId = null;

    public ?TemporaryUploadedFile $file = null;

    /** @var array<int, string> */
    public array $sourceHeaders = [];

    /** @var array<int, array<string, string>> */
    public array $parsedRows = [];

    public string $dateColumn = '';

    public string $descriptionColumn = '';

    public string $referenceColumn = '';

    public string $debitColumn = '';

    public string $creditColumn = '';

    public string $valueDateColumn = '';

    public string $statementFrom = '';

    public string $statementTo = '';

    public string $openingBalance = '';

    public string $closingBalance = '';

    public string $currency = 'USD';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.bank.reconcile');
        $this->currency = $this->school->base_currency ?? 'USD';
    }

    public function updatedFile(): void
    {
        if ($this->file === null) {
            return;
        }

        $contents = $this->file->get();

        if ($contents === false) {
            $this->addError('file', __('Could not read the uploaded file.'));

            return;
        }

        $rows = app(CsvReader::class)->parse($contents);

        $this->sourceHeaders = $rows === [] ? [] : array_keys(reset($rows));
        $this->parsedRows = $rows;
    }

    public function import(): void
    {
        $this->validate([
            'bankAccountId' => ['required', 'integer'],
            'file' => ['required', 'file'],
            'dateColumn' => ['required', 'string'],
            'descriptionColumn' => ['required', 'string'],
            'statementFrom' => ['required', 'date'],
            'statementTo' => ['required', 'date', 'after_or_equal:statementFrom'],
            'openingBalance' => ['required', 'numeric'],
            'closingBalance' => ['required', 'numeric'],
        ]);

        if ($this->debitColumn === '' && $this->creditColumn === '') {
            $this->addError('creditColumn', __('Map at least a debit or a credit column.'));

            return;
        }

        $lines = [];

        foreach ($this->parsedRows as $row) {
            $debit = $this->debitColumn !== '' ? (string) ($row[$this->debitColumn] ?? '') : '';
            $credit = $this->creditColumn !== '' ? (string) ($row[$this->creditColumn] ?? '') : '';

            $lines[] = [
                'transaction_date' => $this->normaliseDate((string) ($row[$this->dateColumn] ?? '')),
                'description' => (string) ($row[$this->descriptionColumn] ?? ''),
                'reference' => $this->referenceColumn !== '' ? (($row[$this->referenceColumn] ?? '') !== '' ? $row[$this->referenceColumn] : null) : null,
                'debit_minor' => $debit !== '' ? (int) round((float) $debit * 100) : null,
                'credit_minor' => $credit !== '' ? (int) round((float) $credit * 100) : null,
                'value_date' => $this->valueDateColumn !== '' && ($row[$this->valueDateColumn] ?? '') !== ''
                    ? $this->normaliseDate((string) $row[$this->valueDateColumn]) : null,
            ];
        }

        try {
            $statement = app(ImportBankStatementAction::class)->execute(new ImportBankStatementData(
                schoolId: $this->school->id,
                bankAccountId: (int) $this->bankAccountId,
                statementFrom: $this->statementFrom,
                statementTo: $this->statementTo,
                openingBalanceMinor: (int) round((float) $this->openingBalance * 100),
                closingBalanceMinor: (int) round((float) $this->closingBalance * 100),
                currency: $this->currency,
                lines: $lines,
                importedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.bank.matching', ['school' => $this->school, 'statement' => $statement], navigate: true);
    }

    private function normaliseDate(string $value): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return $value;
        }
    }

    public function render(): View
    {
        return view('finance::bank.import', [
            'bankAccounts' => BankAccount::where('school_id', $this->school->id)->where('is_active', true)->orderBy('bank_name')->get(),
        ]);
    }
}
