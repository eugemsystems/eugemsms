<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\ExpireOfferData;
use Modules\People\Domain\DataObjects\OfferApplicationData;
use Modules\People\Domain\Events\OfferExpired;
use Modules\People\Models\Application;

/**
 * ACT-ExpireOffer (Book C PPL-02 §4/BR-PPL-02-006). An unaccepted
 * offer past `offer_expires_at` lapses and the place returns to the
 * pool, promoting the next waitlisted applicant for the same intake.
 * No scheduler runs this automatically yet — it is called on demand
 * (e.g. from a future daily job) rather than by a time-based trigger
 * built into this pass.
 */
final class ExpireOfferAction extends Action
{
    public function __construct(
        private readonly OfferApplicationAction $offerApplication,
    ) {}

    public function execute(ExpireOfferData $data): Application
    {
        $application = Application::findOrFail($data->applicationId);

        if ($application->status !== 'offered') {
            throw new InvalidStateTransitionException(
                "Only an [offered] application can expire; this one is [{$application->status}].",
                ['status' => $application->status],
            );
        }

        if ($application->offer_expires_at === null || $application->offer_expires_at->isFuture()) {
            throw new InvalidStateTransitionException(
                'This offer has not yet reached its expiry date.',
                ['offer_expires_at' => $application->offer_expires_at?->toIso8601String()],
            );
        }

        return $this->transaction(function () use ($application): Application {
            $application->update(['status' => 'expired']);
            $application->intake->decrement('places_offered');

            $next = Application::query()
                ->where('intake_id', $application->intake_id)
                ->where('status', 'waitlisted')
                ->whereNotNull('waitlist_position')
                ->orderBy('waitlist_position')
                ->first();

            $promoted = null;

            if ($next !== null) {
                $promoted = $this->offerApplication->execute(new OfferApplicationData(
                    applicationId: $next->id,
                    offeredByUserId: (int) $application->created_by,
                ));
            }

            event(new OfferExpired($application, $promoted));

            return $application;
        });
    }
}
