<?php
// app/Support/Mask.php
namespace App\Support;

class Mask
{
    public static function name(string $nama): string
    {
        $parts = preg_split('/\s+/', trim($nama));
        $first = array_shift($parts);

        return implode(' ', [$first, ...array_map(fn ($p) => mb_substr($p, 0, 1) . '***', $parts)]);
    }
}