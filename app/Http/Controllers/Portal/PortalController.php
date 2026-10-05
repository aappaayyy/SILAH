<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Support\PortalFlow;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function index()
    {
        if ($civitas = PortalFlow::civitas()) {
            return view('portal.index', [
                'step' => 'reset',
                'nama' => $civitas->nama_tersamar,
                'wa'   => $civitas->wa_tersamar,
            ]);
        }

        if ($flow = PortalFlow::register()) {
            $reg = ! empty($flow['registration_id'])
                ? Registration::find($flow['registration_id'])
                : null;

            // Sudah disetujui: flow pendaftaran selesai, kembali ke awal
            if ($reg?->status === RequestStatus::Approved) {
                PortalFlow::clear();
                return redirect()->route('home')
                    ->with('info', 'Pendaftaran Anda sudah disetujui. Akun dikirim ke WhatsApp. Jika belum menerima, masukkan NIM Anda di bawah untuk meminta akun baru.');
            }

            if ($reg) {
                return view('portal.index', [
                    'step'    => 'menunggu',
                    'reg'     => $reg,
                    'antrean' => $reg->status === RequestStatus::Pending
                        ? Registration::pending()->where('id', '<', $reg->id)->count() + 1
                        : null,
                ]);
            }

            return view('portal.index', [
                'step'    => 'daftar',
                'prefill' => PortalFlow::prefill($flow['identifier']),
            ]);
        }

        if (session()->has('wa')) {
            return view('portal.index', ['step' => 'selesai', 'wa' => session('wa')]);
        }

        return view('portal.index', ['step' => 'cek']);
    }

    // Tombol "Cek akun lain" / "Bukan Anda?" / "Kembali"
    public function cancel()
    {
        PortalFlow::clear();

        return redirect()->route('home');
    }

    // Pengajuan ditolak, pengguna ingin mengajukan lagi
    public function reapply()
    {
        if ($flow = PortalFlow::register()) {
            $flow['registration_id'] = null;
            session()->put('register_flow', $flow);
        }

        return redirect()->route('home');
    }
}
