<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Comms\Domain\Exceptions\CalendarFeedTokenInvalidException;
use Modules\Comms\Domain\Support\CalendarAudienceFilter;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\CalendarFeedToken;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-GenerateIcalFeed (Book I COM-06 §4 ⭐/BR-COM-06-008
 * (AC-COM-06-005)). Only `is_public = 1` events are ever eligible —
 * checked BEFORE the audience filter, not after, so a staff-only
 * event can never leak through a token that happens to carry
 * `whole_school` scope. Audience filtering reuses
 * `CalendarAudienceFilter` with the TOKEN's own scope standing in for
 * a viewer's live profile — a token scoped to one section/level sees
 * exactly what a real viewer in that section/level would.
 */
final class GenerateIcalFeedAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly CalendarAudienceFilter $audienceFilter,
    ) {}

    public function execute(string $token): string
    {
        $feedToken = CalendarFeedToken::where('token', $token)->first();

        if ($feedToken === null || $feedToken->isRevoked()) {
            throw CalendarFeedTokenInvalidException::forToken();
        }

        $sectionId = $feedToken->audience_scope === 'section' ? $feedToken->audience_scope_id : null;
        $gradeLevelId = $feedToken->audience_scope === 'level' ? $feedToken->audience_scope_id : null;

        $events = CalendarEvent::where('school_id', $feedToken->school_id)
            ->where('is_public', true)
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (CalendarEvent $event): bool => $this->audienceFilter->isVisible($event, false, $sectionId, $gradeLevelId));

        return $this->render($events);
    }

    /**
     * @param  Collection<int, CalendarEvent>  $events
     */
    private function render($events): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//sERP//Calendar//EN'];

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$event->ulid}@serp";
            $lines[] = 'DTSTART:'.$event->starts_at->utc()->format('Ymd\THis\Z');

            if ($event->ends_at !== null) {
                $lines[] = 'DTEND:'.$event->ends_at->utc()->format('Ymd\THis\Z');
            }

            $lines[] = 'SUMMARY:'.$this->escape($event->title);

            if ($event->description !== null) {
                $lines[] = 'DESCRIPTION:'.$this->escape($event->description);
            }

            if ($event->location !== null) {
                $lines[] = 'LOCATION:'.$this->escape($event->location);
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $value);
    }
}
