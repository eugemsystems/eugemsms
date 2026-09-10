<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\BulkClassData;
use Modules\Core\Domain\DataObjects\Schools\BulkResult;

/**
 * ACT-BulkCreateClasses (Book A CORE-02 §3). Runs each row through
 * `CreateClassAction` inside one transaction — a validation failure on
 * any row rolls the whole batch back, so a partial year's worth of
 * classes never gets created.
 */
final class BulkCreateClassesAction extends Action
{
    public function __construct(
        private readonly CreateClassAction $createClass,
    ) {}

    public function execute(BulkClassData $data): BulkResult
    {
        return $this->transaction(function () use ($data): BulkResult {
            $createdIds = [];

            foreach ($data->classes as $classData) {
                $createdIds[] = $this->createClass->execute($classData)->id;
            }

            return new BulkResult(count($createdIds), $createdIds);
        });
    }
}
