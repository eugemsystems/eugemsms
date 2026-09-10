<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Reporting\Models\AccountingExport;

/**
 * @extends Factory<AccountingExport>
 */
class AccountingExportFactory extends Factory
{
    protected $model = AccountingExport::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'target_system' => 'generic_csv',
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'journal_count' => 0,
            'export_file_id' => 1,
            'exported_by' => User::factory(),
            'exported_at' => now(),
        ];
    }
}
