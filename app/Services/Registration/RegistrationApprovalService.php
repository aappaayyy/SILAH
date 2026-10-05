<?php
// app/Services/Registration/RegistrationApprovalService.php
namespace App\Services\Registration;

use App\Enums\RequestStatus;
use App\Enums\WhatsappStatus;
use App\Enums\WhatsappType;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\AuditLogs;
use App\Models\Civitas;
use App\Models\HotspotAccount;
use App\Models\Registration;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Hotspot\Credentials;
use App\Support\Phone;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RegistrationApprovalService
{
    /** Data pengajuan bentrok dengan data master yang sudah ada? */
    public function hasConflict(Registration $reg): bool
    {
        return Civitas::query()
            ->where('nim_nidn', $reg->nim_nidn)
            ->orWhere('no_wa', $reg->no_wa)
            ->when($reg->email, fn ($q, $e) => $q->orWhere('email', $e))
            ->exists();
    }

    public function approve(Registration $reg, User $admin, Request $req): void
    {
        try {
            [$account, $message, $civitas] = Cache::lock("approve-lock:{$reg->id}", 15)
                ->block(3, fn () => DB::transaction(fn () => $this->createRecords($reg, $admin)));
        } catch (LockTimeoutException) {
            $this->fail('Pengajuan sedang diproses admin lain. Muat ulang halaman.');
        }

        $password = Credentials::password();

        try {
            Bus::chain([
                (new SyncMikrotikUser($account->id, $message->id, $password))->onQueue('default'),
                (new SendWhatsappMessage($message->id, Credentials::message($account->username, $password, new: true)))->onQueue('wa'),
            ])->dispatch();
        } catch (\Throwable $e) {
            // Data sudah tersimpan; tandai gagal agar pengguna bisa meminta akun lewat portal (reset)
            $message->update(['status' => WhatsappStatus::Failed, 'error' => 'dispatch_failed']);
            throw $e;
        }

        AuditLogs::record('registration.approved', $reg, $req, [
            'civitas_id' => $civitas->id,
            'wa_to'      => Phone::mask($civitas->no_wa),
        ]);
    }

    /** @return array{0: HotspotAccount, 1: WhatsappMessage, 2: Civitas} */
    private function createRecords(Registration $input, User $admin): array
    {
        $reg = Registration::lockForUpdate()->findOrFail($input->id);

        if ($reg->status !== RequestStatus::Pending) {
            $this->fail('Pengajuan ini sudah diproses.');
        }
        if ($this->hasConflict($reg)) {
            $this->fail('NIM/NIDN, nomor WhatsApp, atau email sudah ada di data master. Periksa sebelum menyetujui.');
        }

        $civitas = Civitas::create([
            'nim_nidn'  => $reg->nim_nidn,
            'nama'      => $reg->nama,
            'email'     => $reg->email,
            'no_wa'     => $reg->no_wa,
            'tipe'      => $reg->tipe,
            'unit'      => $reg->unit,
            'is_active' => true,
        ]);

        // Mungkin sudah ada di MikroTik (hasil impor) tetapi belum tertaut
        $account = HotspotAccount::where('username', strtolower($civitas->nim_nidn))->first();

        if ($account?->civitas_id) {
            $this->fail('Username sudah terikat ke civitas lain. Selesaikan di data akun hotspot.');
        }

        if ($account) {
            $account->update(['civitas_id' => $civitas->id]);
        } else {
            $account = HotspotAccount::create([
                'civitas_id' => $civitas->id,
                'username'   => $civitas->nim_nidn,
                'profile'    => config('mikrotik.default_profile'),
            ]);
        }

        $account->markResetRequested();

        $message = $account->whatsappMessages()->create([
            'to'   => $civitas->no_wa,
            'type' => WhatsappType::AkunBaru,
        ]);

        $reg->update([
            'status'      => RequestStatus::Approved,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'civitas_id'  => $civitas->id,
        ]);

        return [$account, $message, $civitas];
    }

    public function reject(Registration $reg, User $admin, string $alasan, Request $req): void
    {
        $message = DB::transaction(function () use ($reg, $admin, $alasan) {
            $reg = Registration::lockForUpdate()->findOrFail($reg->id);

            if ($reg->status !== RequestStatus::Pending) {
                $this->fail('Pengajuan ini sudah diproses.');
            }

            $reg->update([
                'status'       => RequestStatus::Rejected,
                'alasan_tolak' => $alasan,
                'reviewed_by'  => $admin->id,
                'reviewed_at'  => now(),
            ]);

            return WhatsappMessage::create([
                'to'           => $reg->no_wa,
                'type'         => WhatsappType::PendaftaranDitolak,
                'related_type' => $reg->getMorphClass(),
                'related_id'   => $reg->id,
            ]);
        });

        SendWhatsappMessage::dispatch(
            $message->id,
            "Pendaftaran akun hotspot Anda belum dapat disetujui.\nAlasan: {$alasan}\n\nAnda dapat mengajukan ulang melalui portal."
        )->onQueue('wa');

        AuditLogs::record('registration.rejected', $reg, $req, ['alasan' => $alasan]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['form' => $message]);
    }
}