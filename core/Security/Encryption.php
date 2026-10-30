<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Security/Encryption.php
 * Purpose  : Symmetric encryption/decryption utility.
 *            Uses AES-256-CBC with HMAC-SHA256 verification.
 *            Protects sensitive stored values (e.g., backup
 *            credentials, API keys in system_settings).
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Security;

use AAM\Config\Application;

class Encryption
{
    private const CIPHER    = 'AES-256-CBC';
    private const HMAC_ALGO = 'sha256';

    /**
     * Encrypt a string value.
     * Returns base64-encoded: HMAC.IV.CipherText
     *
     * @throws \RuntimeException if encryption key is not configured
     */
    public static function encrypt(string $value): string
    {
        $key = self::getKey();

        $iv         = random_bytes(16);
        $ciphertext = openssl_encrypt($value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        $mac     = hash_hmac(self::HMAC_ALGO, $iv . $ciphertext, $key, true);
        $payload = base64_encode($mac . $iv . $ciphertext);

        return $payload;
    }

    /**
     * Decrypt an encrypted value.
     * Returns original string or null if tampered / invalid.
     */
    public static function decrypt(string $payload): ?string
    {
        $key  = self::getKey();
        $raw  = base64_decode($payload, true);

        if ($raw === false || strlen($raw) < 48) {
            return null;
        }

        $mac        = substr($raw, 0, 32);
        $iv         = substr($raw, 32, 16);
        $ciphertext = substr($raw, 48);

        // Verify HMAC before decrypting (prevents padding oracle attacks)
        $expected = hash_hmac(self::HMAC_ALGO, $iv . $ciphertext, $key, true);
        if (!hash_equals($expected, $mac)) {
            return null; // Tampered
        }

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Get the encryption key from configuration.
     * Derives a 32-byte key from the configured key string.
     */
    private static function getKey(): string
    {
        $configKey = (string) Application::get('security.encryption_key', '');

        if (empty($configKey)) {
            throw new \RuntimeException(
                'ENCRYPTION_KEY is not configured. Set it in your .env file.'
            );
        }

        // Derive a consistent 32-byte key
        return hash('sha256', $configKey, true);
    }
}
