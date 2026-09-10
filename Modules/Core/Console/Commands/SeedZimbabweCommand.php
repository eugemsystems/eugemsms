<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Actions\Install\SeedZimbabweBaselineAction;
use Modules\Core\Domain\DataObjects\Install\SeedPackData;
use Modules\Core\Models\School;

/**
 * `php artisan serp:seed:zimbabwe --school={ulid} --packs=coa,grading,roles`
 * (Book A CORE-01 §6).
 */
final class SeedZimbabweCommand extends Command
{
    protected $signature = 'serp:seed:zimbabwe {--school= : The target school\'s ULID} {--packs= : Comma-separated pack codes}';

    protected $description = 'Run one or more Zimbabwe baseline seed packs against a school.';

    public function handle(SeedZimbabweBaselineAction $action): int
    {
        $ulid = $this->option('school');
        $packsOption = $this->option('packs');

        if (! is_string($ulid) || $ulid === '') {
            $this->error('--school={ulid} is required.');

            return self::FAILURE;
        }

        $school = School::where('ulid', $ulid)->first();

        if ($school === null) {
            $this->error("No school found with ULID [{$ulid}].");

            return self::FAILURE;
        }

        $packs = is_string($packsOption) && $packsOption !== ''
            ? array_map('trim', explode(',', $packsOption))
            : [];

        if ($packs === []) {
            $this->error('--packs=code,code is required.');

            return self::FAILURE;
        }

        $result = $action->execute(new SeedPackData(schoolId: $school->id, packs: $packs));

        foreach ($result->outcomes as $outcome) {
            $this->line(($outcome->ran ? '<fg=green>✓</> ' : '<fg=yellow>-</> ').$outcome->packCode.': '.$outcome->message);
        }

        $skipped = array_diff($packs, array_map(fn ($o) => $o->packCode, $result->outcomes));

        foreach ($skipped as $code) {
            $this->line("<fg=yellow>-</> {$code}: unknown pack, skipped.");
        }

        return self::SUCCESS;
    }
}
