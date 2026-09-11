<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Core\Models\School;

/**
 * @extends Factory<AcquisitionRequest>
 */
class AcquisitionRequestFactory extends Factory
{
    protected $model = AcquisitionRequest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'requested_title' => 'New Physics Textbook Form 4',
            'requested_by' => User::factory(),
            'copies_requested' => 5,
            'status' => 'requested',
        ];
    }
}
