<?php
// app/Support/PortalFlow.php
namespace App\Support;

use App\Models\Civitas;

class PortalFlow
{
    /** Civitas dari flow reset; $consume = true berarti sekali pakai (pull). */
    public static function civitas(bool $consume = false): ?Civitas
    {
        $flow = $consume ? session()->pull('reset_flow') : session('reset_flow');

        if (! $flow || $flow['expires_at'] < now()->timestamp) {
            session()->forget('reset_flow');
            return null;
        }

        return Civitas::aktif()->find($flow['civitas_id']);
    }

    /** Data flow pendaftaran (identifier yang tadi diketik), atau null jika kedaluwarsa. */
    public static function register(): ?array
    {
        $flow = session('register_flow');

        if (! $flow || $flow['expires_at'] < now()->timestamp) {
            session()->forget('register_flow');
            return null;
        }

        return $flow;
    }

    public static function clear(): void
    {
        session()->forget(['reset_flow', 'register_flow']);
    }

    /** Tebak isian awal formulir daftar dari apa yang tadi diketik pengguna. */
    public static function prefill(string $identifier): array
    {
        return match (true) {
            str_contains($identifier, '@')            => ['email' => $identifier],
            (bool) preg_match('/^(\+?62|08)\d+$/', $identifier) => ['no_wa' => $identifier],
            default                                   => ['nim_nidn' => $identifier],
        };
    }
}