<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\OfferApplicationData;
use Modules\People\Domain\Events\ApplicationOffered;
use Modules\People\Models\Application;

/**
 * ACT-OfferApplication (Book C PPL-02 §4/BR-PPL-02-006). The offer's
 * expiry is what `ExpireOfferAction` later checks — an unaccepted
 * offer past this date returns its place to the pool.
 */
final class OfferApplicationAction extends Action
{
    private const array OFFERABLE_FROM = ['submitted', 'under_review', 'exam_completed', 'interview_completed', 'waitlisted'];

    public function execute(OfferApplicationData $data): Application
    {
        $application = Application::findOrFail($data->applicationId);

        if (! in_array($application->status, self::OFFERABLE_FROM, true)) {
            throw new InvalidStateTransitionException(
                "An offer can only be made from {$this->allowedList()}; this application is [{$application->status}].",
                ['status' => $application->status],
            );
        }

        return $this->transaction(function () use ($application, $data): Application {
            $application->update([
                'status' => 'offered',
                'offer_made_at' => Carbon::now(),
                'offer_expires_at' => Carbon::now()->addDays($data->offerValidDays),
                'waitlist_position' => null,
            ]);

            $application->intake->increment('places_offered');

            event(new ApplicationOffered($application));

            return $application;
        });
    }

    private function allowedList(): string
    {
        return '['.implode(', ', self::OFFERABLE_FROM).']';
    }
}
