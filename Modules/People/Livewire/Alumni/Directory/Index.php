<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Alumni\Directory;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\AlumniHouseGroup;
use Modules\People\Models\Alumnus;
use Modules\People\Models\Student;

/**
 * `Alumni\Directory\Index` (Book K PPL-06 §5, `alumni.view`). By year group,
 * searchable. Alumni are created automatically at graduation
 * (BR-PPL-06-001), so there is no "add alumnus" here. An alumnus who has
 * opted out of contact is shown plainly marked — staff must see who not to
 * approach.
 */
#[Title('Alumni directory')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $graduationYear = null;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('alumni.view');
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $alumni = Alumnus::query()
            ->when($this->graduationYear !== null, fn ($q) => $q->where('graduation_year', $this->graduationYear))
            ->when(in_array($this->statusFilter, ['active', 'unreachable', 'deceased', 'opted_out'], true), fn ($q) => $q->where('status', $this->statusFilter))
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('admission_number', 'like', $like)
                ->orWhereIn('student_id', Student::query()->where(fn ($s) => $s->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->select('id'))))
            ->orderByDesc('graduation_year')->orderBy('admission_number')->limit(300)->get();

        return view('people::alumni.directory', [
            'alumni' => $alumni,
            'students' => Student::query()->whereIn('id', $alumni->pluck('student_id'))->get()->keyBy('id'),
            'years' => AlumniHouseGroup::query()->orderByDesc('graduation_year')->get(['graduation_year', 'group_name']),
        ]);
    }
}
