<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Encryption;

use Illuminate\Encryption\Encrypter;

/**
 * Book G BRD-06 §4/BR-BRD-06-004, BRD-08 §5 — clinical and safeguarding
 * free text is encrypted with "a key separate from general application
 * encryption". The key here is derived from `config('app.key')` via
 * HMAC-SHA256 with a fixed, purpose-specific label — a value encrypted
 * with the secondary encrypter is NOT decryptable via the default
 * `Crypt` facade (a different 32-byte key, not merely a different
 * cipher instance of the same key), which is the property that
 * actually matters here. True independent key management (a separate
 * KMS/HSM-held secret, rotatable without touching `APP_KEY`) is a
 * deployment/ops concern this pass does not implement — same honest
 * boundary as `FinancialAuditLogEntry`'s deferred DB-grant REVOKE.
 */
final class SecondaryEncrypter
{
    private static ?Encrypter $instance = null;

    public static function instance(): Encrypter
    {
        return self::$instance ??= new Encrypter(self::deriveKey(), (string) config('app.cipher', 'AES-256-CBC'));
    }

    /**
     * Test-only: forces the next call to re-derive the key, in case
     * `config('app.key')` was changed mid-test.
     */
    public static function forgetInstance(): void
    {
        self::$instance = null;
    }

    private static function deriveKey(): string
    {
        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? (string) base64_decode(substr($appKey, 7), true) : $appKey;

        return hash_hmac('sha256', 'welfare.secondary_encryption_key.v1', $raw, true);
    }
}
