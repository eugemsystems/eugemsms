<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Zimsec\Analysis;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\AnalyseZimsecPassRatesAction;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Zimsec\Analysis` (Book H3 CMP-01 §4, `zimsec.view`,
 * read-only). By-teacher breakdown is deliberately not shown — see
 * `AnalyseZimsecPassRatesAction`'s own documented scope boundary on
 * why that axis isn't built.
 */
#[Title('ZIMSEC pass-rate analysis')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public int $registrationId = 0;

    /** @var array{bySubject: array<int, array<string, mixed>>, byClass: array<int, array<string, mixed>>, historical: array<int, array<string, mixed>>}|null */
    public ?array $result = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('zimsec.view');

        $this->registrationId = (int) (ZimsecRegistration::where('school_id', $school->id)->orderByDesc('id')->first()?->id);

        if ($this->registrationId !== 0) {
            $this->analyse();
        }
    }

    public function analyse(): void
    {
        $this->authorizePermission('zimsec.view');

        if ($this->registrationId === 0) {
            $this->result = null;

            return;
        }

        $result = app(AnalyseZimsecPassRatesAction::class)->execute($this->registrationId);

        $this->result = [
            'bySubject' => $result->bySubject,
            'byClass' => $result->byClass,
            'historical' => $result->historical,
        ];
    }

    public function render(): View
    {
        return view('compliance::zimsec.analysis.index', [
            'registrations' => ZimsecRegistration::where('school_id', $this->school->id)->orderByDesc('id')->get(),
        ]);
    }
}
