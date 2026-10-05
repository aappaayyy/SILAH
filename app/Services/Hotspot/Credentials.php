<?php
// app/Services/Hotspot/Credentials.php
namespace App\Services\Hotspot;

class Credentials
{
    // Tanpa karakter yang mudah tertukar (0/o, 1/l/i)
    public static function password(int $length = 10): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        $max   = strlen($chars) - 1;
        $out   = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }

        return $out;
    }

    public static function message(string $username, string $password, bool $new = false): string
    {
        $head = $new ? "Pendaftaran Anda disetujui.\nAkun Hotspot Kampus" : 'Akun Hotspot Kampus';

        return "{$head}\nUsername: {$username}\nPassword: {$password}\n\n"
            . 'Jangan bagikan kepada siapa pun. Jika Anda tidak merasa mendaftar, segera hubungi helpdesk.';
    }
}