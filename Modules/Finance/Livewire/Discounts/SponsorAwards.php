<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Discounts;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * `Finance\Awards\Sponsors` (Book K FIN-07 §5, `finance.award.grant`).
 * Sponsor-funded awards. These never post a discount: the sponsor is billed
 * through a PPL-03 fee liability and the school's income is untouched
 * (BR-FIN-07-010) — so this view links each learner to their liabilities
 * editor, where the sponsor's liability can be seen, instead of showing any
 * discount figure.
 */
#[Title('Sponsor awards')]
#[Layout('layouts.app')]
final class SponsorAwards extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.award.grant');
    }

    public function render(): View
    {
        $awards = DiscountAward::query()->whereNotNull('sponsor_guardian_id')->orderByDesc('id')->limit(200)->get();

        return view('finance::discounts.sponsors', [
            'awards' => $awards,
            'students' => Student::query()->whereIn('id', $awards->pluck('student_id'))->get()->keyBy('id'),
            'sponsors' => Guardian::query()->whereIn('id', $awards->pluck('sponsor_guardian_id'))->get()->keyBy('id'),
            'schemeNames' => DiscountScheme::query()->whereIn('id', $awards->pluck('scheme_id'))->pluck('name', 'id'),
        ]);
    }
}
