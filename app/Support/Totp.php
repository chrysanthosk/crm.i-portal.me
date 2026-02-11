<?php

namespace App\Support;

class Totp
{
    // RFC 4648 base32 alphabet
    private const B32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $length = 20): string
    {
        $bytes = random_bytes($length);
        return self::base32Encode($bytes);
    }

    public static function verifyCode(string $base32Secret, string $code, int $window = 1, int $step = 30): bool
    {
        $code = preg_replace('/\D+/', '', $code ?? '');
        if (strlen($code) !== 6) {
            return false;
        }

        $time = time();
        for ($i = -$window; $i <= $window; $i++) {
            $t = $time + ($i * $step);
            if (hash_equals(self::generateCode($base32Secret, $t, $step), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function generateCode(string $base32Secret, int $timestamp = null, int $step = 30): string
    {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, $step);

        $key = self::base32Decode($base32Secret);
        $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte counter

        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);

        $value = unpack('N', $part)[1] & 0x7FFFFFFF;
        $otp = $value % 1000000;

        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    public static function otpauthUrl(string $accountEmail, string $issuer, string $base32Secret): string
    {
        $label = rawurlencode($issuer . ':' . $accountEmail);
        $issuerEnc = rawurlencode($issuer);
        return "otpauth://totp/{$label}?secret=" . $base32Secret . "&issuer={$issuerEnc}&algorithm=SHA1&digits=6&period=30";
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);

        $bits = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::B32_ALPHABET, $b32[$i]);
            if ($val === false) {
                continue;
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }

        return $bytes;
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= self::B32_ALPHABET[bindec($chunk)];
        }

        return $out;
    }
}
