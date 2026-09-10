<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintUpdate;
use Modules\Core\Models\School;

/**
 * @extends Factory<ComplaintUpdate>
 */
class ComplaintUpdateFactory extends Factory
{
    protected $model = ComplaintUpdate::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'complaint_id' => Complaint::factory()->for($school),
            'update_type' => 'comment',
            'content' => 'We are looking into this.',
            'visible_to_raiser' => true,
            'posted_by' => User::factory(),
            'posted_at' => now(),
        ];
    }
}
