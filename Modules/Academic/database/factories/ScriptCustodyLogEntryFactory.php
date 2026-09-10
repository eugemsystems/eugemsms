<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\ScriptCustodyLogEntry;
use Modules\Core\Models\School;

/**
 * @extends Factory<ScriptCustodyLogEntry>
 */
class ScriptCustodyLogEntryFactory extends Factory
{
    protected $model = ScriptCustodyLogEntry::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'batch_id' => ScriptBatch::factory()->for($school),
            'action' => 'collected',
            'script_count' => 30,
            'occurred_at' => now(),
            'recorded_by' => User::factory(),
        ];
    }
}
