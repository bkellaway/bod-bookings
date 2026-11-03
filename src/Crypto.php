<?php
declare(strict_types=1);

/** Symmetric encryption for secrets at rest (refresh tokens). Key = APP_KEY (64 hex chars). */
final class Crypto
{
    private static function key(): string
    {
        $hex = Env::require('APP_KEY');
        if (!preg_match('/^[0-9a-f]{64}$/i', $hex)) {
            throw new RuntimeException('APP_KEY must be 64 hex characters (32 bytes).');
        }
        return hex2bin($hex);
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box   = sodium_crypto_secretbox($plain, $nonce, self::key());
        return base64_encode($nonce . $box);
    }

    public static function decrypt(string $b64): string
    {
        $raw   = base64_decode($b64, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Corrupt ciphertext');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box   = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($box, $nonce, self::key());
        if ($plain === false) {
            throw new RuntimeException('Decryption failed (APP_KEY changed?)');
        }
        return $plain;
    }

    /** URL-safe random token (43 chars for 32 bytes). */
    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
