<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\UpdateHouseData;
use Modules\Core\Models\House;

/**
 * ACT-UpdateHouse (Book A CORE-02 §5, admin UI follow-up).
 */
final class UpdateHouseAction extends Action
{
    public function execute(UpdateHouseData $data): House
    {
        $house = House::withoutGlobalScopes()
            ->where('id', $data->houseId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        Validator::make(
            [
                'code' => $data->code,
                'name' => $data->name,
                'colour' => $data->colour,
            ],
            [
                'code' => [
                    'required', 'string', 'max:20',
                    'unique:houses,code,'.$house->id.',id,school_id,'.$data->schoolId,
                ],
                'name' => ['required', 'string', 'max:60'],
                'colour' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ],
        )->validate();

        return $this->transaction(function () use ($house, $data): House {
            $house->update([
                'code' => $data->code,
                'name' => $data->name,
                'colour' => $data->colour,
                'motto' => $data->motto,
            ]);

            return $house;
        });
    }
}
