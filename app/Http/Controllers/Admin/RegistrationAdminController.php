<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLogs;
use App\Models\Registration;
use App\Services\Registration\RegistrationApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegistrationAdminController extends Controller
{
    public function __construct(private RegistrationApprovalService $service) {}

    public function index(Request $request)
    {
        $status = RequestStatus::tryFrom((string) $request->query('status', 'Pending')) ?? RequestStatus::Pending;

        $rows = Registration::where('status', $status)
            ->latest('id')
            ->limit(1000)
            ->get(['id', 'nama', 'nim_nidn', 'tipe', 'no_wa', 'dokumen_path', 'status', 'created_at']);

        // Query mentah agar kunci hasil tetap string (bukan enum)
        $counts = DB::table('registrations')
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.registrations.index', compact('rows', 'status', 'counts'));
    }

    public function show(Registration $registration)
    {
        $registration->load('reviewer:id,name');

        return view('admin.registrations.show', [
            'reg'      => $registration,
            'conflict' => $registration->status === RequestStatus::Pending
                && $this->service->hasConflict($registration),
        ]);
    }

    // Dokumen KTM hanya bisa dibuka lewat route ini (disk private, wajib login admin)
    public function document(Registration $registration, Request $request)
    {
        abort_unless(
            $registration->dokumen_path && Storage::disk('local')->exists($registration->dokumen_path),
            404
        );

        AuditLogs::record('registration.document_viewed', $registration, $request);

        return Storage::disk('local')->response($registration->dokumen_path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    public function approve(Request $request, Registration $registration)
    {
        $request->validate(['verified' => ['accepted']], [
            'verified.accepted' => 'Centang konfirmasi verifikasi terlebih dahulu.',
        ]);

        $this->service->approve($registration, $request->user(), $request);

        return redirect()->route('admin.registrations.index')
            ->with('success', "Pengajuan {$registration->nama} disetujui. Akun sedang dikirim ke WhatsApp.");
    }

    public function reject(Request $request, Registration $registration)
    {
        $data = $request->validate([
            'alasan_tolak' => ['required', 'string', 'min:5', 'max:200'],
        ]);

        $this->service->reject($registration, $request->user(), $data['alasan_tolak'], $request);

        return redirect()->route('admin.registrations.index')
            ->with('success', "Pengajuan {$registration->nama} ditolak dan pemohon diberi tahu lewat WhatsApp.");
    }
}
