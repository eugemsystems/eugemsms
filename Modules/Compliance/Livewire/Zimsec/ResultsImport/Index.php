<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Zimsec\ResultsImport;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\ImportZimsecResultsAction;
use Modules\Compliance\Domain\DataObjects\ImportZimsecResultsData;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Compliance\Models\ZimsecResult;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Zimsec\ResultsImport` (Book H3 CMP-01 §4,
 * `zimsec.results.import`). CSV pasted as plain text, one row per
 * line (`candidate_number,subject_code,subject_name,grade,points`) —
 * the same raw-textarea-over-structured-upload pattern this book's
 * own `Payroll\Statutory\Config` already established for a
 * low-volume, infrequent admin input. Unmatched candidate numbers are
 * reported, never silently dropped (BR-CMP-01-010, AC-CMP-01-005).
 */
#[Title('ZIMSEC results import')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $registrationId = 0;

    public string $rowsText = '';

    /** @var array<int, array{candidate_number: string, subject_code: string, reason: string}> */
    public array $unmatched = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('zimsec.results.import');

        $this->registrationId = (int) (ZimsecRegistration::where('school_id', $school->id)->orderByDesc('id')->first()?->id);
    }

    public function import(): void
    {
        $this->authorizePermission('zimsec.results.import');

        $this->validate(['rowsText' => ['required', 'string']]);

        $rows = [];

        foreach (preg_split('/\r?\n/', trim($this->rowsText)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));

            if (count($parts) < 4) {
                continue;
            }

            $rows[] = [
                'candidate_number' => $parts[0],
                'subject_code' => $parts[1],
                'subject_name' => $parts[2],
                'grade' => $parts[3],
                'points' => $parts[4] ?? null,
            ];
        }

        if ($rows === []) {
            $this->addError('rowsText', __('No valid rows found — one per line: candidate_number,subject_code,subject_name,grade,points'));

            return;
        }

        $result = app(ImportZimsecResultsAction::class)->execute(new ImportZimsecResultsData(
            registrationId: $this->registrationId,
            importedByUserId: (int) auth()->id(),
            rows: $rows,
        ));

        $this->unmatched = $result->unmatched;
        $this->reset(['rowsText']);
        $this->toast(__(':imported imported, :unmatched unmatched.', ['imported' => $result->imported->count(), 'unmatched' => count($result->unmatched)]));
    }

    public function render(): View
    {
        $results = $this->registrationId !== 0
            ? ZimsecResult::where('registration_id', $this->registrationId)->orderBy('candidate_number')->get()
            : collect();

        return view('compliance::zimsec.results-import.index', [
            'registrations' => ZimsecRegistration::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'results' => $results,
        ]);
    }
}
