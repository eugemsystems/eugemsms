<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\DataObjects\ReportFieldDefinition;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Domain\Support\ReportFieldAccess;

/**
 * ACT-GetAvailableFieldsForUser (Book J INT-01 §3 ⭐/BR-INT-01-002
 * (AC-INT-01-001)). THE field picker's own data source — a field the
 * running user's live permission set excludes is simply absent from
 * what this returns, so a class teacher's picker never shows
 * `staff.basic_salary_minor` in the first place. Evaluated fresh on
 * every call, never cached against a stale permission snapshot.
 */
final class GetAvailableFieldsForUserAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ReportFieldAccess $access,
    ) {}

    /**
     * @return array<int, ReportFieldDefinition>
     */
    public function execute(string $entityKey, User $runner): array
    {
        return array_values(array_filter(
            ReportFieldRegistry::fieldsForEntity($entityKey),
            fn (ReportFieldDefinition $field): bool => $this->access->mayRead($field, $runner),
        ));
    }
}
