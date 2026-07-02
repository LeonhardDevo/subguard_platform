<?php

namespace App\Support;

class Totp
{
    /**
     * Base32 alphabet used for decoding TOTP secrets.
     */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Verify a TOTP code against a base32-encoded secret.
     *
     * @param string $secret  Base32-encoded TOTP secret.
     * @param string $code    6-digit code to validate.
     * @param int    $window  Number of steps to check in each direction (default 1 = ±30 s).
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $time = time();
        $step = 30;

        for ($i = -$window; $i <= $window; $i++) {
            $counter = (int) floor($time / $step) + $i;
            $expected = self::generateForCounter($secret, $counter);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a 6-digit TOTP for a given counter.
     */
    private static function generateForCounter(string $secret, int $counter): string
    {
        $decoded = self::base32Decode($secret);

        $counterBytes = pack('N*', 0) . pack('N*', $counter);

        $hash = hash_hmac('sha1', $counterBytes, $decoded, true);

        $offset = ord($hash[19]) & 0x0F;

        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $value % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a base32-encoded string.
     */
    private static function base32Decode(string $input): string
    {
        $input = strtoupper(rtrim($input, '='));

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos(self::BASE32_ALPHABET, $input[$i]);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
