<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Drivers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Transport\Domain\Actions\CheckDriverDocumentExpiryAction;
use Modules\Transport\Domain\Actions\CreateDriverAction;
use Modules\Transport\Domain\DataObjects\CreateDriverData;
use Modules\Transport\Models\Driver;

/**
 * `Drivers\Index` (Book H2 OPS-01 §5/BR-OPS-01-004, `transport.manage`).
 */
#[Title('Drivers')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $staffId = null;

    public string $licenceNumber = '';

    /** @var array<int, string> */
    public array $licenceClasses = [];

    public string $licenceExpiresOn = '';

    public ?string $medicalExpiresOn = null;

    public ?string $defensiveExpiresOn = null;

    public ?int $yearsExperience = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('transport.manage');
    }

    public function create(): void
    {
        $this->validate([
            'staffId' => ['required', 'integer'],
            'licenceNumber' => ['required', 'string', 'max:40'],
            'licenceExpiresOn' => ['required', 'date'],
        ]);

        app(CreateDriverAction::class)->execute(new CreateDriverData(
            schoolId: $this->school->id,
            staffId: (int) $this->staffId,
            licenceNumber: $this->licenceNumber,
            licenceClasses: $this->licenceClasses !== [] ? $this->licenceClasses : ['4'],
            licenceExpiresOn: Carbon::parse($this->licenceExpiresOn),
            medicalExpiresOn: $this->medicalExpiresOn !== null && $this->medicalExpiresOn !== '' ? Carbon::parse($this->medicalExpiresOn) : null,
            defensiveExpiresOn: $this->defensiveExpiresOn !== null && $this->defensiveExpiresOn !== '' ? Carbon::parse($this->defensiveExpiresOn) : null,
            yearsExperience: $this->yearsExperience,
        ));

        $this->reset(['staffId', 'licenceNumber', 'licenceClasses', 'medicalExpiresOn', 'defensiveExpiresOn', 'yearsExperience']);
        $this->toast(__('Driver registered.'));
    }

    public function checkExpiry(): void
    {
        $flagged = app(CheckDriverDocumentExpiryAction::class)->execute($this->school->id);
        $this->toast(__(':count driver(s) moved to expired_documents.', ['count' => $flagged->count()]));
    }

    public function render(): View
    {
        return view('transport::drivers.index', [
            'drivers' => Driver::with('staff')->where('school_id', $this->school->id)->orderBy('licence_expires_on')->get(),
            'staffMembers' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->limit(200)->get(),
        ]);
    }
}
