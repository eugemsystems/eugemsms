<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Enquiry;
use Modules\People\Models\EnquiryActivity;

/**
 * @extends Factory<EnquiryActivity>
 */
class EnquiryActivityFactory extends Factory
{
    protected $model = EnquiryActivity::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'enquiry_id' => Enquiry::factory()->for($school), 'activity_type' => 'call', 'summary' => 'Called the parent', 'performed_by' => User::factory(), 'occurred_at' => now(),
        ];
    }
}
