<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use InvalidArgumentException;

/**
 * Shared check for a scheme of work's topic list: coverage is keyed by a
 * topic's position, so the list is re-indexed from zero, and each topic must
 * name a teaching week and a title.
 */
final class NormalisesPlannedTopics
{
    /**
     * @param  array<int|string, array<string, mixed>>  $topics
     * @return array<int, array<string, mixed>>
     */
    public static function normalise(array $topics): array
    {
        $topics = array_values($topics);

        if ($topics === [] || count($topics) > 60) {
            throw new InvalidArgumentException('A scheme of work needs between 1 and 60 topics.');
        }

        foreach ($topics as $topic) {
            $week = $topic['week'] ?? null;
            $title = trim((string) ($topic['topic'] ?? ''));

            if (! is_numeric($week) || (int) $week < 1 || (int) $week > 20 || $title === '' || mb_strlen($title) > 200) {
                throw new InvalidArgumentException('Every topic needs a teaching week (1-20) and a title of up to 200 characters.');
            }
        }

        return $topics;
    }
}
