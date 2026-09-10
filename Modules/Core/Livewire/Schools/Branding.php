<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Schools;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Actions\Schools\UpdateSchoolProfileAction;
use Modules\Core\Domain\DataObjects\Schools\UpdateSchoolData;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Core\Schools\Branding` (Book A CORE-02 §5). Logo, crest, letterhead,
 * colours, with a live preview.
 */
#[Title('School branding')]
#[Layout('layouts.app')]
final class Branding extends Component
{
    use InteractsWithSchool;
    use Toasts;
    use WithFileUploads;

    public string $primaryColour = '#1a3a5c';

    public ?string $secondaryColour = null;

    public ?TemporaryUploadedFile $logo = null;

    public ?TemporaryUploadedFile $crest = null;

    public ?TemporaryUploadedFile $letterhead = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);

        $this->primaryColour = $school->primary_colour;
        $this->secondaryColour = $school->secondary_colour;
    }

    public function save(): void
    {
        $this->validate([
            'primaryColour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondaryColour' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'crest' => ['nullable', 'image', 'max:2048'],
            'letterhead' => ['nullable', 'image', 'max:4096'],
        ]);

        $logoPath = $this->logo?->store('schools/'.$this->school->id, 'public') ?: null;
        $crestPath = $this->crest?->store('schools/'.$this->school->id, 'public') ?: null;
        $letterheadPath = $this->letterhead?->store('schools/'.$this->school->id, 'public') ?: null;

        $updated = app(UpdateSchoolProfileAction::class)->execute(new UpdateSchoolData(
            schoolId: $this->school->id,
            actingUserId: (int) Auth::id(),
            logoPath: $logoPath,
            crestPath: $crestPath,
            letterheadPath: $letterheadPath,
            primaryColour: $this->primaryColour,
            secondaryColour: $this->secondaryColour,
        ));

        $this->school = $updated;
        $this->reset(['logo', 'crest', 'letterhead']);

        $this->toast(__('Branding updated.'));
    }

    public function render(): View
    {
        return view('core::schools.branding', [
            'logoUrl' => $this->school->logo_path !== null ? Storage::disk('public')->url($this->school->logo_path) : null,
            'crestUrl' => $this->school->crest_path !== null ? Storage::disk('public')->url($this->school->crest_path) : null,
            'letterheadUrl' => $this->school->letterhead_path !== null ? Storage::disk('public')->url($this->school->letterhead_path) : null,
        ]);
    }
}
