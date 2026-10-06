<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\People\Models\StudentDocument;

/**
 * @extends Factory<StudentDocument>
 */
class StudentDocumentFactory extends Factory
{
    protected $model = StudentDocument::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school), 'document_type' => 'birth_certificate', 'file_id' => File::factory()->state(['school_id' => $school]),
        ];
    }
}
