<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Models\House;

/**
 * `Auditable` exercised against the real `houses` table — no dedicated
 * fixture migration needed, same trick as `TestApprovable`.
 */
class TestAuditableHouse extends House
{
    use Auditable;

    protected $table = 'houses';

    /**
     * @var array<int, string>
     */
    public array $auditExcluded = ['colour'];
}
