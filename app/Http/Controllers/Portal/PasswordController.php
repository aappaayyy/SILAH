<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Services\Hotspot\ChangeOutcome;
use App\Services\Hotspot\PasswordChangeService;
use App\Support\PortalFlow;

class PasswordController extends Controller
{
    public function store(ChangePasswordRequest $request, PasswordChangeService $service)
    {
        $civitas = PortalFlow::civitas();

        if (! $civitas) {
            return redirect()->route('home')
                ->withErrors(['identifier' => 'Sesi berakhir. Silakan cek akun kembali.']);
        }

        $new = $request->validated('password');

        if (strtolower($new) === strtolower($civitas->nim_nidn)) {
            return back()->withErrors(['password' => 'Password tidak boleh sama dengan username.']);
        }

        $r = $service->change($civitas, $request->validated('password_lama'), $new, $request);

        if ($r->outcome === ChangeOutcome::Queued) {
            PortalFlow::clear();

            return redirect()->route('home')->with(['wa' => $civitas->wa_tersamar ?? '-', 'mode' => 'ganti']);
        }

        $min = $r->retryAfter ? (int) ceil($r->retryAfter / 60) : null;

        return back()->withErrors(match ($r->outcome) {
            ChangeOutcome::Wrong       => ['password_lama' => "Password lama salah. Sisa percobaan: {$r->remaining}."],
            ChangeOutcome::Locked      => ['password_lama' => "Terlalu banyak percobaan salah. Coba lagi dalam {$min} menit, atau minta akun baru lewat WhatsApp."],
            ChangeOutcome::Unavailable => ['password_lama' => 'Akun ini belum bisa memakai ganti password. Gunakan "Kirim akun baru ke WhatsApp".'],
            ChangeOutcome::Blocked     => ['password_lama' => 'Akun tidak dapat diubah otomatis. Silakan hubungi helpdesk.'],
            default                    => ['password_lama' => 'Permintaan sebelumnya masih diproses. Coba lagi sebentar.'],
        });
    }
}
