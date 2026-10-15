<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLogs;
use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class WhatsappController extends Controller
{
    public function index(Request $request)
    {
        AuditLogs::record('whatsapp.status_viewed', null, $request);

        return view('admin.whatsapp', ['canManage' => $request->user()->isSuperadmin()]);
    }

    // Dipanggil berkala oleh JavaScript di halaman
    public function status(Request $request, WhatsappClient $wa)
    {
        try {
            $s = $wa->status();
        } catch (\Throwable) {
            $s = ['state' => 'offline', 'pending' => 0, 'qr' => null, 'connectedAt' => null];
        }

        // QR sama dengan kunci login WhatsApp: hanya untuk superadmin
        if (! $request->user()->isSuperadmin()) {
            $s['qr'] = null;
        }

        return response()
            ->json(Arr::only($s, ['state', 'pending', 'qr', 'connectedAt']))
            ->header('Cache-Control', 'no-store, private');
    }

    public function reset(Request $request, WhatsappClient $wa)
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        try {
            $wa->resetSession();
        } catch (RequestException $e) {
            $msg = $e->response->status() === 409
                ? 'Masih ada pesan dalam antrean. Tunggu hingga terkirim, lalu coba lagi.'
                : 'Gateway menolak permintaan reset.';
            return back()->withErrors(['wa' => $msg]);
        } catch (\Throwable) {
            return back()->withErrors(['wa' => 'Gateway tidak dapat dihubungi.']);
        }

        AuditLogs::record('whatsapp.session_reset', null, $request);

        return back()->with('success', 'Sesi direset. QR baru akan tampil dalam beberapa detik.');
    }
}
