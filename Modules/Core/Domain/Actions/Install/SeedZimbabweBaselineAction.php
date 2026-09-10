<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\SeedPackData;
use Modules\Core\Domain\DataObjects\Install\SeedResult;
use Modules\Core\Domain\Registry\SeedPackRegistry;
use Modules\Core\Models\School;

/**
 * ACT-SeedZimbabweBaseline (Book A CORE-01 §3/§7).
 */
final class SeedZimbabweBaselineAction extends Action
{
    public function execute(SeedPackData $data): SeedResult
    {
        $school = School::findOrFail($data->schoolId);

        return $this->transaction(function () use ($data, $school): SeedResult {
            $outcomes = [];

            foreach ($data->packs as $code) {
                $pack = SeedPackRegistry::find($code);

                if ($pack === null || ! $pack->isAvailable()) {
                    continue;
                }

                $outcomes[] = $pack->run($school);
            }

            return new SeedResult($outcomes);
        });
    }
}
