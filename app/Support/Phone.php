<?php

namespace App\Support;

class Phone
{
    /**
     * Normalisasi format nomor telepon ke format E.164 Indonesia (62xxx).
     */
    public static function normalize(?string $n): ?string
    {
        if (blank($n)) {
            return null;
        }

        // Hapus semua karakter selain angka
        $digits = preg_replace('/\D+/', '', $n);

        if (empty($digits)) {
            return null;
        }

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0')  => '62' . substr($digits, 1),
            str_starts_with($digits, '8')  => '62' . $digits,
            default                        => $digits,
        };
    }

    /**
     * Menyamarkan (masking) bagian tengah nomor telepon untuk privasi.
     * Contoh: "628123456789" -> "6281****789"
     */
    public static function mask(?string $n): string
    {
        if (blank($n)) {
            return '';
        }

        // Normalisasi terlebih dahulu
        $normalized = static::normalize($n) ?? '';
        $length = strlen($normalized);

        // Jika nomor terlalu pendek (kurang dari 7 digit), kembalikan masker sederhana
        if ($length < 7) {
            return str_repeat('*', $length);
        }

        return substr($normalized, 0, 4) . '****' . substr($normalized, -3);
    }

    /**
     * Validasi sederhana apakah string merupakan nomor HP Indonesia yang valid.
     */
    public static function isValid(?string $n): bool
    {
        $normalized = static::normalize($n);

        if (!$normalized) {
            return false;
        }

        // Nomor WA/HP Indonesia umumnya diawali 628 dan panjangnya 10-15 digit
        return (bool) preg_match('/^628\d{7,12}$/', $normalized);
    }
}