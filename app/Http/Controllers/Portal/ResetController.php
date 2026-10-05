<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Civitas;
use App\Services\Hotspot\HotspotResetService;
use App\Services\Hotspot\ResetOutcome;
use App\Support\PortalFlow;
use Illuminate\Http\Request;

class ResetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, HotspotResetService $service)
    {
        $civitas = PortalFlow::civitas(consume: true);   // sekali pakai

        if (! $civitas) {
            return redirect()->route('home')
                ->withErrors(['identifier' => 'Sesi berakhir. Silakan cek akun kembali.']);
        }

        $result = $service->reset($civitas, $request);

        if ($result->queued()) {
            return redirect()->route('home')->with('wa', $civitas->wa_tersamar);
        }

        $menit = $result->retryAfter ? (int) ceil($result->retryAfter / 60) : null;

        $message = match ($result->outcome) {
            ResetOutcome::Cooldown   => "Akun sudah dikirim ke WhatsApp Anda, silakan cek kembali. Coba lagi dalam {$menit} menit bila belum diterima.",
            ResetOutcome::TooMany    => 'Batas reset harian tercapai. Coba lagi besok atau hubungi helpdesk.',
            ResetOutcome::NoWhatsapp => 'Nomor WhatsApp Anda belum terdaftar. Hubungi helpdesk untuk pembaruan nomor.',
            ResetOutcome::Blocked    => 'Akun tidak dapat direset otomatis. Silakan hubungi helpdesk.',
            default                  => 'Permintaan sedang diproses, coba beberapa saat lagi.',
        };

        return redirect()->route('home')->withErrors(['identifier' => $message]);
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Civitas $civitas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Civitas $civitas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Civitas $civitas)
    {
        //
    }
}
