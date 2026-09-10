<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

/**
 * Book C PPL-01 §6/BR-PPL-01-011. `applicant`/`enrolled` are entry
 * states owned by `PPL-02` (not reachable through `ACT-ChangeStudentStatus`
 * once a learner has a `students` row created here, since `ACT-CreateStudent`
 * lands directly on `enrolled` or `active`).
 */
final class StudentStatusMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    private const array TRANSITIONS = [
        'applicant' => ['enrolled'],
        'enrolled' => ['active'],
        'active' => ['suspended', 'transferred', 'withdrawn', 'graduated', 'deceased'],
        'suspended' => ['active', 'transferred', 'withdrawn', 'deceased'],
        'transferred' => ['archived'],
        'withdrawn' => ['active', 'archived'],
        'graduated' => ['archived'],
        'deceased' => ['archived'],
        'archived' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedFrom(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }
}
