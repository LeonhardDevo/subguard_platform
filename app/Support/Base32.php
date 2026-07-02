<?php

namespace App\Support;

class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function random(int $bytes = 20): string
    {
        $random = random_bytes($bytes);
        return self::encode($random);
    }

    public static function encode(string $data): string
    {
        $bits = '';
        $result = '';

        for ($i = 0; $i < strlen($data); $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        while (strlen($bits) % 5 !== 0) {
            $bits .= '0';
        }

        for ($i = 0; $i < strlen($bits); $i += 5) {
            $index = bindec(substr($bits, $i, 5));
            $result .= self::ALPHABET[$index];
        }

        return $result;
    }

    public static function decode(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $bits = '';
        $result = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $val = strpos(self::ALPHABET, $base32[$i]);
            if ($val === false) {
                throw new \InvalidArgumentException('Invalid Base32 character: ' . $base32[$i]);
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        while (strlen($bits) % 8 !== 0) {
            $bits = substr($bits, 0, -1);
        }

        for ($i = 0; $i < strlen($bits); $i += 8) {
            $result .= chr(bindec(substr($bits, $i, 8)));
        }

        return $result;
    }
}
