<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationDocument;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    protected $model = ApplicationDocument::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'application_id' => Application::factory()->for($school), 'document_type' => 'birth_certificate', 'file_id' => File::factory()->state(['school_id' => $school]),
        ];
    }
}
