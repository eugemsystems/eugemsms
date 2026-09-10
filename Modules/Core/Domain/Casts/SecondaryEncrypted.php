<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Support\Encryption\SecondaryEncrypter;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
final class SecondaryEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return SecondaryEncrypter::instance()->decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : SecondaryEncrypter::instance()->encryptString($value);
    }
}
