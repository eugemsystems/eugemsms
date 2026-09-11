<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\UpdateSectionData;
use Modules\Core\Models\SchoolSection;

/**
 * ACT-UpdateSection (Book A CORE-02 §5, admin UI follow-up).
 */
final class UpdateSectionAction extends Action
{
    public function execute(UpdateSectionData $data): SchoolSection
    {
        $section = SchoolSection::withoutGlobalScopes()
            ->where('id', $data->sectionId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        Validator::make(
            [
                'code' => $data->code,
                'name' => $data->name,
                'type' => $data->type,
            ],
            [
                'code' => [
                    'required', 'string', 'max:20',
                    'unique:school_sections,code,'.$section->id.',id,school_id,'.$data->schoolId,
                ],
                'name' => ['required', 'string', 'max:100'],
                'type' => ['required', 'in:ecd,primary,secondary,sixth_form'],
            ],
        )->validate();

        return $this->transaction(function () use ($section, $data): SchoolSection {
            $section->update([
                'code' => $data->code,
                'name' => $data->name,
                'type' => $data->type,
            ]);

            return $section;
        });
    }
}
