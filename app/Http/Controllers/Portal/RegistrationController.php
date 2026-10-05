<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Services\Registration\RegistrationService;
use App\Support\PortalFlow;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function store(StoreRegistrationRequest $request, RegistrationService $service)
    {
        // Form hanya boleh dikirim setelah melalui "cek akun"
        if (! PortalFlow::register()) {
            return redirect()->route('home')
                ->withErrors(['identifier' => 'Sesi berakhir. Silakan cek akun kembali.']);
        }

        $reg = $service->submit(
            $request->safe()->except('dokumen'),
            $request->file('dokumen'),
            $request,
        );

        // Langsung tampilkan status "menunggu" pada halaman yang sama
        $request->session()->regenerate();
        session()->put('register_flow', [
            'identifier'      => $reg->nim_nidn,
            'registration_id' => $reg->id,
            'expires_at'      => now()->addMinutes(30)->timestamp,
        ]);

        return redirect()->route('home');
    }
}
