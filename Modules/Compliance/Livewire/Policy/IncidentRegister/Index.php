<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Policy\IncidentRegister;

use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\GenerateConsolidatedIncidentRegisterAction;
use Modules\Compliance\Domain\DataObjects\ConsolidatedIncidentRegisterEntry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Policy\IncidentRegister` (Book H3 CMP-04 §3 ⭐,
 * `policy.view`, read-only). Consolidates health, discipline,
 * security, transport and data-protection incidents for board
 * reporting — safeguarding is EXCLUDED entirely; this screen only ever
 * renders what `GenerateConsolidatedIncidentRegisterAction` returns,
 * and that action never queries `Modules\Welfare\Models\SafeguardingCase`
 * at all (BR-CMP-04-008, AC-CMP-04-003). `severity` is `null` for
 * sources whose own model carries no severity column — rendered as a
 * dash, never fabricated.
 */
#[Title('Consolidated incident register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $periodStart = '';

    public string $periodEnd = '';

    /** @var array<int, array{source: string, occurredAt: Carbon|CarbonInterface, type: string, description: string, severity: string|null}> */
    public array $entries = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('policy.view');

        $this->periodStart = now()->subMonth()->toDateString();
        $this->periodEnd = now()->toDateString();

        $this->generate();
    }

    public function generate(): void
    {
        $this->authorizePermission('policy.view');

        $this->entries = app(GenerateConsolidatedIncidentRegisterAction::class)->execute(
            $this->school->id,
            $this->periodStart,
            $this->periodEnd,
        )->map(fn (ConsolidatedIncidentRegisterEntry $entry): array => [
            'source' => $entry->source,
            'occurredAt' => $entry->occurredAt,
            'type' => $entry->type,
            'description' => $entry->description,
            'severity' => $entry->severity,
        ])->values()->all();
    }

    public function render(): View
    {
        return view('compliance::policy.incident-register.index');
    }
}
