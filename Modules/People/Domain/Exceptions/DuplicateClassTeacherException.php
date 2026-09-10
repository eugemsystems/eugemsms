<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-007. A class may have exactly one
 * `is_class_teacher = 1` allocation per term.
 */
class DuplicateClassTeacherException extends DomainException
{
    public static function forClass(int $classId, int $termId): self
    {
        return new self(
            "Class [{$classId}] already has a class teacher for term [{$termId}].",
            ['class_id' => $classId, 'term_id' => $termId],
        );
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_CLASS_TEACHER';
    }
}
