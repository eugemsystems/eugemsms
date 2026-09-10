<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\CreateHouseData;
use Modules\Core\Models\House;

/**
 * ACT-CreateHouse (Book A CORE-02 §3).
 */
final class CreateHouseAction extends Action
{
    public function execute(CreateHouseData $data): House
    {
        Validator::make(
            [
                'code' => $data->code,
                'name' => $data->name,
                'colour' => $data->colour,
            ],
            [
                'code' => [
                    'required', 'string', 'max:20',
                    'unique:houses,code,NULL,id,school_id,'.$data->schoolId,
                ],
                'name' => ['required', 'string', 'max:60'],
                'colour' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ],
        )->validate();

        return $this->transaction(fn (): House => House::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'colour' => $data->colour,
            'motto' => $data->motto,
            'housemaster_id' => $data->housemasterId,
            'is_active' => true,
        ]));
    }
}
