<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Complaints;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CreateComplaintCategoryAction;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Complaints\Categories` (Book I COM-08 §2, `complaints.manage`).
 * Not in the spec's §4 screens table — this pass's own addition, because
 * the backend could raise a complaint but had no way to create the
 * category every complaint needs, and a category carries the SLA
 * (BR-COM-08-003) and the safeguarding routing flag (BR-COM-08-006).
 * Categories are create-only: changing an SLA later would silently
 * disagree with the due dates already stamped on open complaints.
 */
#[Title('Complaint categories')]
#[Layout('layouts.app')]
final class Categories extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public int $slaHours = 72;

    public bool $isSafeguardingTrigger = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('complaints.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('complaints.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('complaint_categories', 'code')->where('school_id', $this->school->id)],
            'name' => ['required', 'string', 'max:120'],
            'slaHours' => ['required', 'integer', 'min:1', 'max:2160'],
        ]);

        app(CreateComplaintCategoryAction::class)->execute($this->school->id, $this->code, $this->name, $this->slaHours, $this->isSafeguardingTrigger);

        $this->reset(['code', 'name', 'isSafeguardingTrigger']);
        $this->slaHours = 72;
        $this->toast(__('Category created.'));
    }

    public function render(): View
    {
        return view('comms::complaints.categories', [
            'categories' => ComplaintCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
