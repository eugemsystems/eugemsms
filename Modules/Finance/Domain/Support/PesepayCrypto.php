<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Modules\Finance\Domain\Exceptions\GatewayRequestFailedException;

/**
 * Pesepay wraps every request and response body in `{"payload": "<base64 AES-256-CBC>"}`. The key
 * is the merchant's 32-character encryption key and the IV is its first 16 characters. Because only
 * the holder of that key can produce a payload that decrypts to valid JSON, a successful decryption
 * is also what authenticates an inbound result callback.
 */
final class PesepayCrypto
{
    public function __construct(
        private readonly string $encryptionKey,
    ) {
        if (strlen($encryptionKey) !== 32) {
            throw new GatewayRequestFailedException('The Pesepay encryption key must be 32 characters.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function encrypt(array $data): string
    {
        $cipher = openssl_encrypt((string) json_encode($data), 'aes-256-cbc', $this->encryptionKey, 0, substr($this->encryptionKey, 0, 16));

        if ($cipher === false) {
            throw new GatewayRequestFailedException('Could not encrypt the Pesepay request.');
        }

        return $cipher;
    }

    /**
     * @return array<string, mixed>|null null when the text is not a payload this key can open
     */
    public function decrypt(string $payload): ?array
    {
        $plain = openssl_decrypt($payload, 'aes-256-cbc', $this->encryptionKey, 0, substr($this->encryptionKey, 0, 16));

        if ($plain === false) {
            return null;
        }

        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : null;
    }
}
