<?php

namespace App\Support\Security;

/**
 * Makes the one-time password that staff hand to a student or colleague, for
 * example "Qk7x-vN2m-Rt9x". It meets the password policy (10+ characters, upper
 * and lower case, a digit) by construction, avoids look-alike characters
 * (0 O 1 l I), and is meant to be changed at first sign-in.
 */
final class TemporaryPassword
{
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    public static function generate(): string
    {
        $all = self::UPPER.self::LOWER.self::DIGITS;

        // The first group is guaranteed one of each kind; the rest is random.
        $first = self::pick(self::UPPER, 1).self::pick(self::LOWER, 1).self::pick(self::DIGITS, 1).self::pick($all, 1);

        return $first.'-'.self::pick($all, 4).'-'.self::pick($all, 4);
    }

    private static function pick(string $set, int $count): string
    {
        $out = '';

        for ($i = 0; $i < $count; $i++) {
            $out .= $set[random_int(0, strlen($set) - 1)];
        }

        return $out;
    }
}
