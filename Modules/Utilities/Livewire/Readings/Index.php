<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Readings;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Utilities\Domain\Actions\RecordMeterReadingAction;
use Modules\Utilities\Domain\DataObjects\RecordMeterReadingData;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\MeterReading;

/**
 * `Readings\Index` (Book H2 OPS-04 §6 ⭐/BR-OPS-04-006/007/008,
 * `utilities.read`). Append-only — every row this screen creates is a
 * new reading, never an edit of one already there. A reading lower
 * than the previous one, or consumption beyond the rolling-average
 * tolerance, always comes back flagged.
 */
#[Title('Meter readings')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $meterId = null;

    public string $readOn = '';

    public string $reading = '';

    public string $readingMethod = 'manual';

    public ?int $prepaidAssetAccountId = null;

    public ?MeterReading $lastResult = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('utilities.read');
        $this->readOn = Carbon::now()->toDateString();
    }

    public function record(): void
    {
        $this->validate([
            'meterId' => ['required', 'integer'],
            'readOn' => ['required', 'date'],
            'reading' => ['required', 'numeric'],
            'readingMethod' => ['required', 'string'],
        ]);

        $this->lastResult = app(RecordMeterReadingAction::class)->execute(new RecordMeterReadingData(
            schoolId: $this->school->id,
            meterId: (int) $this->meterId,
            readOn: Carbon::parse($this->readOn),
            reading: (float) $this->reading,
            readingMethod: $this->readingMethod,
            readByUserId: (int) auth()->id(),
            academicYearId: SessionContext::yearId(),
            termId: SessionContext::termId(),
            prepaidAssetAccountId: $this->prepaidAssetAccountId,
            postedByUserId: (int) auth()->id(),
        ));

        $this->reset(['reading']);

        if ($this->lastResult->is_anomaly) {
            $this->toast(__('Reading recorded — flagged as an anomaly.'), 'warning');
        } else {
            $this->toast(__('Reading recorded.'));
        }
    }

    public function render(): View
    {
        return view('utilities::readings.index', [
            'readings' => MeterReading::with('meter')->where('school_id', $this->school->id)->orderByDesc('read_on')->limit(50)->get(),
            'meters' => Meter::where('school_id', $this->school->id)->orderBy('meter_number')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
