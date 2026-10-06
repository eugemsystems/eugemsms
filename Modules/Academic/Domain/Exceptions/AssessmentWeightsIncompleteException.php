<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-05 §5/BR-ACA-05-004/AC-ACA-05-001. A subject's assessment weights
 * must total 100% before results are computed; the subject is named with its
 * shortfall (or excess).
 */
class AssessmentWeightsIncompleteException extends DomainException
{
    /**
     * @param  array<int, array{subject_id: int, subject: string, total_percent: float, difference: float}>  $problems
     */
    public static function forSubjects(array $problems): self
    {
        $named = array_map(
            fn (array $p): string => sprintf('%s totals %s%% (%s%s%%)', $p['subject'], rtrim(rtrim(number_format($p['total_percent'], 2), '0'), '.'), $p['difference'] < 0 ? 'short by ' : 'over by ', rtrim(rtrim(number_format(abs($p['difference']), 2), '0'), '.')),
            $problems,
        );

        return new self('Assessment weights must total 100%: '.implode('; ', $named).'.', ['subjects' => $problems]);
    }

    public function errorCode(): string
    {
        return 'ASSESSMENT_WEIGHTS_INCOMPLETE';
    }
}
