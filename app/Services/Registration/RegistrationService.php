<?php
// app/Services/Registration/RegistrationService.php
namespace App\Services\Registration;

use App\Enums\RequestStatus;
use App\Enums\Tipe;
use App\Models\AuditLogs;
use App\Models\Civitas;
use App\Models\Registration;
use App\Support\Phone;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    private const DAILY_LIMIT_PER_IP = 3;

    /** @throws ValidationException */
    public function submit(array $data, ?UploadedFile $dokumen, Request $req): Registration
    {
        $ipKey = 'register-ip:' . $req->ip();

        if (RateLimiter::tooManyAttempts($ipKey, self::DAILY_LIMIT_PER_IP)) {
            $this->fail('Terlalu banyak pengajuan dari perangkat ini hari ini. Coba lagi besok atau hubungi helpdesk.');
        }

        try {
            // Cegah dua pengajuan bersamaan untuk NIM yang sama (klik ganda / dua tab)
            $reg = Cache::lock('register-lock:' . $data['nim_nidn'], 10)
                ->block(3, fn () => $this->create($data, $dokumen, $req));
        } catch (LockTimeoutException) {
            $this->fail('Pengajuan sedang diproses. Mohon tunggu sebentar.');
        }

        RateLimiter::hit($ipKey, 86400);

        AuditLogs::record('registration.submitted', $reg, $req, [
            'tipe'        => $reg->tipe->value,
            'has_dokumen' => $reg->dokumen_path !== null,
            'wa_to'       => Phone::mask($reg->no_wa),
        ]);

        return $reg;
    }

    private function create(array $data, ?UploadedFile $dokumen, Request $req): Registration
    {
        // Sudah ada di data master: seharusnya lewat alur reset, bukan daftar
        $exists = Civitas::query()
            ->where('nim_nidn', $data['nim_nidn'])
            ->orWhere('no_wa', $data['no_wa'])
            ->when($data['email'] ?? null, fn ($q, $e) => $q->orWhere('email', $e))
            ->exists();

        if ($exists) {
            $this->fail('Data sudah terdaftar di sistem. Gunakan menu cek akun, atau hubungi helpdesk bila nomor/email berubah.');
        }

        $pending = Registration::pending()
            ->where(fn ($q) => $q->where('nim_nidn', $data['nim_nidn'])->orWhere('no_wa', $data['no_wa']))
            ->exists();

        if ($pending) {
            $this->fail('Pengajuan dengan data tersebut masih menunggu verifikasi admin.');
        }

        // Berkas hanya disimpan untuk mahasiswa
        $path = null;
        if ($data['tipe'] === Tipe::Mahasiswa->value && $dokumen) {
            $path = $dokumen->storeAs(
                'registrations/' . now()->format('Y/m'),
                Str::uuid() . '.' . $dokumen->extension(),   // nama acak, bukan nama asli dari pengguna
                'local'                                       // disk private, tidak bisa diakses lewat URL
            );
        }

        try {
            return Registration::create([
                ...$data,
                'dokumen_path' => $path,
                'status'       => RequestStatus::Pending,
                'ip'           => $req->ip(),
                'user_agent'   => Str::limit($req->userAgent() ?? '', 250, ''),
            ]);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);   // jangan tinggalkan berkas yatim
            }
            throw $e;
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['form' => $message]);
    }
}