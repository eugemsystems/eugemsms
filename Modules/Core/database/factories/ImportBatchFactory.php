<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\File;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\School;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'definition_key' => 'test_importer',
            'source_file_id' => File::factory(),
            'column_mapping' => ['name' => 'name'],
            'status' => 'mapping',
            'imported_by' => User::factory(),
        ];
    }
}
