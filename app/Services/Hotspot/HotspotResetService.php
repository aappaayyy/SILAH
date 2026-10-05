<?php
// app/Services/Hotspot/HotspotResetService.php
namespace App\Services\Hotspot;

use App\Enums\WhatsappStatus;
use App\Enums\WhatsappType;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\AuditLogs;
use App\Models\Civitas;
use App\Models\HotspotAccount;
use App\Models\WhatsappMessage;
use App\Support\Phone;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class HotspotResetService
{
    private const DAILY_LIMIT      = 3;
    private const COOLDOWN_MINUTES = 15;

    public function reset(Civitas $civitas, Request $req): ResetResult
    {
        if (blank($civitas->no_wa)) {
            return ResetResult::of(ResetOutcome::NoWhatsapp);
        }

        try {
            // Satu reset per civitas pada satu waktu (cegah klik ganda / dua tab)
            return Cache::lock("reset-lock:{$civitas->id}", 10)
                ->block(3, fn () => $this->process($civitas, $req));
        } catch (LockTimeoutException) {
            return ResetResult::of(ResetOutcome::Busy);
        }
    }

    private function process(Civitas $civitas, Request $req): ResetResult
    {
        $account = $this->resolveAccount($civitas);

        if ($account === false) {
            AuditLogs::record('hotspot.reset_blocked', $civitas, $req, ['reason' => 'username_conflict']);
            return ResetResult::of(ResetOutcome::Blocked);
        }

        if ($account && ($account->is_disabled || $account->expired_at?->isPast())) {
            AuditLogs::record('hotspot.reset_blocked', $civitas, $req, ['reason' => 'disabled_or_expired']);
            return ResetResult::of(ResetOutcome::Blocked);
        }

        // Pengiriman terakhir gagal -> pengguna boleh mencoba lagi tanpa dihitung
        $bypass = $account?->lastWhatsappFailed() ?? false;

        if (! $bypass) {
            if ($account?->last_reset_at?->gt(now()->subMinutes(self::COOLDOWN_MINUTES))) {
                $wait = $account->last_reset_at->copy()->addMinutes(self::COOLDOWN_MINUTES)->timestamp - now()->timestamp;
                return ResetResult::of(ResetOutcome::Cooldown, max(1, $wait));
            }

            $key = "reset:{$civitas->id}";
            if (RateLimiter::tooManyAttempts($key, self::DAILY_LIMIT)) {
                return ResetResult::of(ResetOutcome::TooMany, RateLimiter::availableIn($key));
            }
            RateLimiter::hit($key, 86400);
        }

        $password = Credentials::password();
        $created  = $account === null;

        // Plaintext TIDAK masuk database; hanya status & jejak pesan
        [$account, $message] = DB::transaction(function () use ($account, $civitas) {
            $account ??= HotspotAccount::create([
                'civitas_id' => $civitas->id,
                'username'   => $civitas->nim_nidn,
                'profile'    => config('mikrotik.default_profile'),
            ]);

            $account->markResetRequested();

            $message = $account->whatsappMessages()->create([
                'to'   => $civitas->no_wa,           // selalu dari data master
                'type' => WhatsappType::AkunReset,
            ]);

            return [$account, $message];
        });

        try {
            Bus::chain([
                (new SyncMikrotikUser($account->id, $message->id, $password))->onQueue('default'),
                (new SendWhatsappMessage($message->id, Credentials::message($account->username, $password)))->onQueue('wa'),
            ])->dispatch();
        } catch (\Throwable $e) {
            // Redis bermasalah: tandai gagal supaya pengguna bisa mencoba lagi tanpa terhitung
            $message->update(['status' => WhatsappStatus::Failed, 'error' => 'dispatch_failed']);
            throw $e;
        }

        AuditLogs::record('hotspot.reset', $civitas, $req, [
            'wa_to'       => Phone::mask($civitas->no_wa),
            'new_account' => $created,
            'bypass'      => $bypass,
        ]);

        return ResetResult::of(ResetOutcome::Queued);
    }

    /**
     * @return HotspotAccount|null|false  null = belum punya akun (akan dibuat), false = konflik
     */
    private function resolveAccount(Civitas $civitas): HotspotAccount|null|false
    {
        if ($account = $civitas->hotspotAccount) {
            return $account;
        }

        // Akun hasil impor yang belum sempat ditautkan oleh hotspot:link
        $existing = HotspotAccount::where('username', strtolower($civitas->nim_nidn))->first();

        if (! $existing) {
            return null;
        }

        if ($existing->civitas_id !== null) {
            return false;   // username sudah milik civitas lain: perlu ditinjau admin
        }

        $existing->update(['civitas_id' => $civitas->id]);
        return $existing;
    }
}