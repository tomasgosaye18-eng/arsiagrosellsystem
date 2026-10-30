<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Security/PasswordUtility.php
 * Purpose  : Password hashing and verification utility.
 *            Uses bcrypt (PHP password_hash).
 *            Enforces password strength rules.
 *            Generates secure temporary passwords.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Security;

use AAM\Config\Application;

class PasswordUtility
{
    /**
     * Hash a password using bcrypt.
     *
     * @param string $plainPassword
     * @return string bcrypt hash
     */
    public static function hash(string $plainPassword): string
    {
        $cost = Application::get('security.bcrypt_cost', 12);
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Verify a plain password against a stored hash.
     *
     * @param string $plainPassword
     * @param string $hash
     */
    public static function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    /**
     * Check if a hash needs to be rehashed (cost factor changed).
     *
     * @param string $hash
     */
    public static function needsRehash(string $hash): bool
    {
        $cost = Application::get('security.bcrypt_cost', 12);
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Validate password strength against the policy defined in config/security.php.
     *
     * Rules enforced:
     *   - Minimum length  : MIN_PASSWORD_LENGTH
     *   - Uppercase letter: REQUIRE_UPPERCASE
     *   - Digit           : REQUIRE_NUMBER
     *   - Special char    : REQUIRE_SPECIAL_CHAR
     *   - Lowercase letter: always required (baseline rule)
     *
     * @param string $password
     * @return array{valid: bool, errors: string[]}
     */
    public static function validate(string $password): array
    {
        $errors = [];

        $minLength = defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 8;
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long.";
        }

        if (defined('REQUIRE_UPPERCASE') && REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        // Lowercase is always required as a baseline — no toggle needed
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (defined('REQUIRE_NUMBER') && REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if (defined('REQUIRE_SPECIAL_CHAR') && REQUIRE_SPECIAL_CHAR && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character (e.g. !@#$).';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Generate a cryptographically secure random password.
     *
     * @param int $length Default 12 characters
     */
    public static function generate(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $digits    = '0123456789';
        $special   = '@#$!';
        $all       = $uppercase . $lowercase . $digits . $special;

        // Ensure at least one of each required character type
        $password  = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill remaining characters randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle the result to avoid predictable prefix pattern
        $passwordArray = str_split($password);
        shuffle($passwordArray);
        return implode('', $passwordArray);
    }

    /**
     * Check if a password matches common weak patterns.
     * Returns the matched weak pattern or empty string.
     */
    public static function isCommonPassword(string $password): bool
    {
        $common = [
            'password', 'password1', '123456', '12345678', 'qwerty',
            'abc123', 'letmein', 'admin', 'admin123', 'welcome',
            'arsi123', 'aam2026', 'university', 'student',
        ];
        return in_array(strtolower($password), $common, true);
    }
}
