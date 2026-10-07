<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Notifications\SetNotificationPreferenceAction;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationPreferenceData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\NotificationPreference;
use Modules\People\Domain\Actions\RequestGuardianContactUpdateAction;
use Modules\People\Domain\DataObjects\RequestGuardianContactUpdateData;
use Modules\People\Models\Guardian;

/**
 * Book C PPL-03 §8: `PATCH /api/v1/me/contact-details` and `GET|PUT
 * /api/v1/me/notification-preferences`, for a guardian token.
 */
final class GuardianProfileController
{
    public function updateContactDetails(Request $request): JsonResponse
    {
        $guardian = $this->guardian($request);
        $data = $request->validate([
            'primary_phone' => ['sometimes', 'string', 'max:20'],
            'alternate_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'whatsapp_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:150'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'province' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        if ($data === []) {
            return ApiResponse::error('VALIDATION_FAILED', 'At least one contact field is required.', 422);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $update = app(RequestGuardianContactUpdateAction::class)->execute(new RequestGuardianContactUpdateData(
                guardianId: $guardian->id,
                changes: $data,
                requestedByUserId: $user->id,
            ));
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }

        return ApiResponse::ok(['status' => $update->status, 'requested_at' => $update->created_at->toIso8601ZuluString()], status: 202);
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $school = SchoolContext::current();
        abort_if($school === null, 404);

        $preferences = NotificationPreference::query()->where('school_id', $school->id)->where('user_id', $user->id)->get();

        return ApiResponse::ok($preferences->map(fn (NotificationPreference $preference): array => [
            'notification_key' => $preference->notification_key,
            'channel' => $preference->channel,
            'is_enabled' => $preference->is_enabled,
        ])->values()->all());
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $school = SchoolContext::current();
        abort_if($school === null, 404);

        $data = $request->validate([
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.channel' => ['required', 'string'],
            'preferences.*.is_enabled' => ['required', 'boolean'],
            'preferences.*.notification_key' => ['nullable', 'string'],
        ]);

        foreach ($data['preferences'] as $preference) {
            app(SetNotificationPreferenceAction::class)->execute(new SetNotificationPreferenceData(
                schoolId: $school->id,
                userId: $user->id,
                channel: $preference['channel'],
                isEnabled: (bool) $preference['is_enabled'],
                notificationKey: $preference['notification_key'] ?? null,
            ));
        }

        return $this->notificationPreferences($request);
    }

    private function guardian(Request $request): Guardian
    {
        /** @var User $user */
        $user = $request->user();

        $guardian = Guardian::query()->where('user_id', $user->id)->first();
        abort_if($guardian === null, 404);

        return $guardian;
    }
}
