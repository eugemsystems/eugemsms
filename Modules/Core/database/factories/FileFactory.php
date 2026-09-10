<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\File;
use Modules\Core\Models\School;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'disk' => 'local',
            'path' => 'school/1/general/'.fake()->uuid().'.pdf',
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => fake()->numberBetween(1000, 500000),
            'hash' => hash('sha256', fake()->uuid()),
            'category' => 'supplier_document',
            'scan_status' => 'clean',
            'uploaded_by' => User::factory(),
        ];
    }
}
