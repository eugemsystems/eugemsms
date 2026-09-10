<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Schools;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\UpdateSchoolProfileAction;
use Modules\Core\Domain\DataObjects\Schools\UpdateSchoolData;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Core\Schools\Profile` (Book A CORE-02 §5). `code` is displayed but
 * never editable here — BR-CORE-02-001.
 */
#[Title('School profile')]
#[Layout('layouts.app')]
final class Profile extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public string $name = '';

    public ?string $shortName = null;

    public ?string $centreNumber = null;

    public ?string $emisCode = null;

    public string $category = 'private';

    public ?string $responsibleAuthority = null;

    public ?string $band = null;

    public ?string $province = null;

    public ?string $district = null;

    public ?string $addressLine1 = null;

    public ?string $addressLine2 = null;

    public ?string $city = null;

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $website = null;

    public ?string $motto = null;

    public string $timezone = 'Africa/Harare';

    public string $locale = 'en_ZW';

    public function mount(School $school): void
    {
        $this->loadSchool($school);

        $this->name = $school->name;
        $this->shortName = $school->short_name;
        $this->centreNumber = $school->centre_number;
        $this->emisCode = $school->emis_code;
        $this->category = $school->category;
        $this->responsibleAuthority = $school->responsible_authority;
        $this->band = $school->band;
        $this->province = $school->province;
        $this->district = $school->district;
        $this->addressLine1 = $school->address_line_1;
        $this->addressLine2 = $school->address_line_2;
        $this->city = $school->city;
        $this->phone = $school->phone;
        $this->email = $school->email;
        $this->website = $school->website;
        $this->motto = $school->motto;
        $this->timezone = $school->timezone;
        $this->locale = $school->locale;
    }

    public function save(): void
    {
        $updated = app(UpdateSchoolProfileAction::class)->execute(new UpdateSchoolData(
            schoolId: $this->school->id,
            actingUserId: (int) Auth::id(),
            name: $this->name,
            shortName: $this->shortName,
            centreNumber: $this->centreNumber,
            emisCode: $this->emisCode,
            category: $this->category,
            responsibleAuthority: $this->responsibleAuthority,
            band: $this->band,
            province: $this->province,
            district: $this->district,
            addressLine1: $this->addressLine1,
            addressLine2: $this->addressLine2,
            city: $this->city,
            phone: $this->phone,
            email: $this->email,
            website: $this->website,
            motto: $this->motto,
            timezone: $this->timezone,
            locale: $this->locale,
        ));

        $this->school = $updated;

        $this->toast(__('School profile updated.'));
    }

    public function render(): View
    {
        return view('core::schools.profile');
    }
}
