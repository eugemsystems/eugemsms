<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Actions\Auth\AuthenticateWebAction;
use Modules\Core\Domain\Actions\Auth\IssueApiTokenAction;
use Modules\Core\Domain\Actions\Auth\RefreshApiTokenAction;
use Modules\Core\Domain\Actions\Auth\RequestOtpAction;
use Modules\Core\Domain\Actions\Auth\RevokeTokenAction;
use Modules\Core\Domain\Actions\Auth\VerifyOtpAction;
use Modules\Core\Domain\DataObjects\Auth\DeviceData;
use Modules\Core\Domain\DataObjects\Auth\IssueTokenData;
use Modules\Core\Domain\DataObjects\Auth\RefreshTokenData;
use Modules\Core\Domain\DataObjects\Auth\RequestOtpData;
use Modules\Core\Domain\DataObjects\Auth\RevokeTokenData;
use Modules\Core\Domain\DataObjects\Auth\TokenPair;
use Modules\Core\Domain\DataObjects\Auth\VerifyOtpData;
use Modules\Core\Domain\DataObjects\Auth\WebLoginData;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\Tenant;

/**
 * `/api/v1/auth/*` (Volume 1 §9.3). Every route validates input, calls exactly one Action
 * and formats the result. Parents sign in with a phone OTP; staff with a password. An
 * account that needs a second factor cannot finish a password login here — it signs in
 * with the OTP flow instead — so the API never issues a token past a required 2FA gate.
 */
final class AuthController
{
    public function requestOtp(Request $request, RequestOtpAction $action): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);

        $action->execute(new RequestOtpData($data['phone'], $this->tenantId($request)));

        return ApiResponse::ok(['sent' => true]);
    }

    public function verifyOtp(Request $request, VerifyOtpAction $action): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:10'],
            ...$this->deviceRules(),
        ]);

        $result = $action->execute(new VerifyOtpData($data['phone'], $data['code'], $this->device($data), $this->tenantId($request), $request->ip()));

        return ApiResponse::ok($this->tokens($result->tokens));
    }

    public function login(Request $request, AuthenticateWebAction $authenticate, IssueApiTokenAction $issue): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            ...$this->deviceRules(),
        ]);

        $result = $authenticate->execute(new WebLoginData($data['identifier'], $data['password'], $this->tenantId($request), $request->ip(), $request->userAgent()));

        if ($result->requiresTwoFactor) {
            return ApiResponse::error('TWO_FACTOR_REQUIRED', 'This account needs a second factor. Sign in with a one-time code instead.', 403);
        }

        $pair = $issue->execute(new IssueTokenData(userId: $result->user->id, device: $this->device($data), ip: $request->ip()));

        return ApiResponse::ok($this->tokens($pair));
    }

    public function refresh(Request $request, RefreshApiTokenAction $action): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string', 'max:200']]);

        return ApiResponse::ok($this->tokens($action->execute(new RefreshTokenData($data['refresh_token'], $request->ip()))));
    }

    public function logout(Request $request, RevokeTokenAction $action): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            $action->execute(new RevokeTokenData((int) $token->getKey(), (int) $request->user()->getAuthIdentifier()));
        }

        return ApiResponse::ok(['signed_out' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceRules(): array
    {
        return [
            'device' => ['required', 'array'],
            'device.name' => ['required', 'string', 'max:100'],
            'device.id' => ['nullable', 'string', 'max:100'],
            'device.platform' => ['nullable', 'string', 'max:30'],
            'device.model' => ['nullable', 'string', 'max:100'],
            'device.app_version' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function device(array $data): DeviceData
    {
        return new DeviceData(
            name: $data['device']['name'],
            id: $data['device']['id'] ?? null,
            platform: $data['device']['platform'] ?? null,
            model: $data['device']['model'] ?? null,
            appVersion: $data['device']['app_version'] ?? null,
        );
    }

    private function tenantId(Request $request): ?int
    {
        $tenant = $request->attributes->get('tenant');

        return $tenant instanceof Tenant ? $tenant->id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function tokens(?TokenPair $pair): array
    {
        abort_if($pair === null, 500);

        return [
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
            'token_type' => 'Bearer',
            'access_expires_at' => $pair->accessTokenExpiresAt->toIso8601ZuluString(),
            'refresh_expires_at' => $pair->refreshTokenExpiresAt->toIso8601ZuluString(),
            'abilities' => $pair->abilities,
        ];
    }
}
