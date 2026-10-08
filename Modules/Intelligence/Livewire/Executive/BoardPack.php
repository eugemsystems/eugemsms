<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Executive;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Intelligence\Domain\Actions\GenerateBoardPackAction;
use Modules\Intelligence\Models\BoardPack as BoardPackRecord;

/**
 * `Intelligence\Executive\BoardPack` (Book J INT-02 §4,
 * `executive.board_pack.generate` — also satisfies Book H3 FIN-12's
 * own `Reports\Board\Pack`, the same "board pack" concept named twice
 * across two books' specs; see `GenerateBoardPackAction`'s own
 * docblock). Assembles the comprehensive pack for a term. The
 * financial section is FIN-12's own income statement, embedded
 * unmodified (BR-INT-02-006, AC-INT-02-003); `collection_rate` is
 * likewise FIN-12's own real collection report, rolled into one
 * whole-school figure. Only the five sections the backend can resolve
 * are offered; `academic` outcomes, the INT-03 risk summary, and
 * FIN-12's own undefined "key ratios" have no resolver and are not
 * listed, rather than producing an empty section. The pack is a
 * content-hashed JSON file in the file vault (no PDF renderer
 * exists), listed here by name.
 */
#[Title('Board pack')]
#[Layout('layouts.app')]
final class BoardPack extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<string, string> */
    public const array SECTIONS = [
        'enrolment' => 'Enrolment',
        'financial' => 'Financial (from the income statement)',
        'staffing' => 'Staffing',
        'boarding' => 'Boarding occupancy',
        'collection_rate' => 'Fee collection rate',
    ];

    public ?int $termId = null;

    /** @var array<int, string> */
    public array $sections = ['enrolment', 'financial', 'staffing', 'boarding', 'collection_rate'];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('executive.board_pack.generate');
    }

    public function generate(): void
    {
        $this->authorizePermission('executive.board_pack.generate');

        $this->validate([
            'termId' => ['required', 'integer'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['in:'.implode(',', array_keys(self::SECTIONS))],
        ]);

        $term = Term::where('school_id', $this->school->id)->findOrFail($this->termId);

        try {
            app(GenerateBoardPackAction::class)->execute($this->school->id, $term->id, array_values($this->sections), (int) auth()->id());
        } catch (InvalidArgumentException $exception) {
            $this->addError('sections', $exception->getMessage());

            return;
        }

        $this->toast(__('Board pack generated.'));
    }

    public function render(): View
    {
        $packs = BoardPackRecord::where('school_id', $this->school->id)->orderByDesc('generated_at')->limit(50)->get();

        return view('intelligence::executive.board-pack', [
            'terms' => Term::where('school_id', $this->school->id)->orderByDesc('starts_on')->limit(12)->get(['id', 'name', 'starts_on']),
            'packs' => $packs,
            'termNames' => Term::where('school_id', $this->school->id)->whereIn('id', $packs->pluck('term_id'))->pluck('name', 'id'),
            'files' => File::where('school_id', $this->school->id)->whereIn('id', $packs->pluck('document_id')->filter())->pluck('original_name', 'id'),
            'sectionLabels' => self::SECTIONS,
        ]);
    }
}
