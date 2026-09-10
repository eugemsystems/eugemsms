<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\CreateSectionData;
use Modules\Core\Domain\Events\Schools\SectionCreated;
use Modules\Core\Models\SchoolSection;

/**
 * ACT-CreateSection (Book A CORE-02 §3).
 */
final class CreateSectionAction extends Action
{
    public function execute(CreateSectionData $data): SchoolSection
    {
        Validator::make(
            [
                'code' => $data->code,
                'name' => $data->name,
                'type' => $data->type,
            ],
            [
                'code' => [
                    'required', 'string', 'max:20',
                    'unique:school_sections,code,NULL,id,school_id,'.$data->schoolId,
                ],
                'name' => ['required', 'string', 'max:100'],
                'type' => ['required', 'in:ecd,primary,secondary,sixth_form'],
            ],
        )->validate();

        return $this->transaction(function () use ($data): SchoolSection {
            $section = SchoolSection::create([
                'school_id' => $data->schoolId,
                'code' => $data->code,
                'name' => $data->name,
                'type' => $data->type,
                'sort_order' => $data->sortOrder,
                'head_user_id' => $data->headUserId,
                'is_active' => true,
            ]);

            event(new SectionCreated($section));

            return $section;
        });
    }
}
