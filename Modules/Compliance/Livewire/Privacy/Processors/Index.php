<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Processors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\RegisterThirdPartyProcessorAction;
use Modules\Compliance\Domain\DataObjects\RegisterThirdPartyProcessorData;
use Modules\Compliance\Models\ThirdPartyProcessor;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Processors` (Book H3 CMP-03 §4, `privacy.manage`).
 * `ThirdPartyProcessor::isCrossBorder()` is computed from `country`,
 * never a separate stored boolean (BR-CMP-03-014) — this screen reads
 * that accessor, never duplicates the comparison.
 */
#[Title('Third-party processors')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $name = '';

    public string $processorType = 'sms';

    public string $dataSharedText = '';

    public string $purpose = '';

    public string $country = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.manage');
    }

    public function register(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:200'],
            'processorType' => ['required', 'in:payment_gateway,sms,whatsapp,cloud_storage,email,analytics,backup'],
            'dataSharedText' => ['required', 'string'],
            'purpose' => ['required', 'string'],
        ]);

        app(RegisterThirdPartyProcessorAction::class)->execute(new RegisterThirdPartyProcessorData(
            schoolId: $this->school->id,
            name: $this->name,
            processorType: $this->processorType,
            dataShared: array_values(array_filter(array_map('trim', explode(',', $this->dataSharedText)))),
            purpose: $this->purpose,
            country: $this->country !== '' ? $this->country : null,
        ));

        $this->reset(['name', 'dataSharedText', 'purpose', 'country']);
        $this->toast(__('Processor registered.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.processors.index', [
            'processors' => ThirdPartyProcessor::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
