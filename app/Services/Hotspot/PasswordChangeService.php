<?php

namespace App\Services\Hotspot;

use App\Enums\WhatsappStatus;
use App\Enums\WhatsappType;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\AuditLogs;
use App\Models\Civitas;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PasswordChangeService
{
    private const TRY_MAX   = 5;     // salah maksimal per 15 menit
    private const TRY_DECAY = 900;
    private const DAY_MAX   = 15;    // salah maksimal per hari

    public function change(Civitas $civitas, string $old, string $new, Request $req): ChangeResult
    {
        $account = $civitas->hotspotAccount;

        if (! $account || ! $account->password_hash) {
            return new ChangeResult(ChangeOutcome::Unavailable);
        }

        if ($account->is_disabled || $account->expired_at?->isPast()) {
            return new ChangeResult(ChangeOutcome::Blocked);
        }

        $short = "pw-try:{$civitas->id}";
        $day   = "pw-day:{$civitas->id}";

        foreach ([[$short, self::TRY_MAX], [$day, self::DAY_MAX]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return new ChangeResult(ChangeOutcome::Locked, RateLimiter::availableIn($key));
            }
        }

        // Hanya percobaan yang SALAH dihitung
        if (! Hash::check($old, $account->password_hash)) {
            RateLimiter::hit($short, self::TRY_DECAY);
            RateLimiter::hit($day, 86400);
            AuditLogs::record('password.change_failed', $civitas, $req);

            return new ChangeResult(ChangeOutcome::Wrong, null, RateLimiter::remaining($short, self::TRY_MAX));
        }

        // Cegah dua perubahan bersamaan sebelum job pertama selesai
        if (! Cache::add("pw-pending:{$civitas->id}", 1, 30)) {
            return new ChangeResult(ChangeOutcome::Busy);
        }

        RateLimiter::clear($short);

        $message = filled($civitas->no_wa)
            ? $account->whatsappMessages()->create(['to' => $civitas->no_wa, 'type' => WhatsappType::PasswordDiubah])
            : null;

        $jobs = [(new SyncMikrotikUser($account->id, $message?->id ?? 0, $new))->onQueue('default')];

        if ($message) {
            $jobs[] = (new SendWhatsappMessage($message->id, Credentials::changed($account->username)))->onQueue('wa');
        }

        try {
            Bus::chain($jobs)->dispatch();
        } catch (\Throwable $e) {
            $message?->update(['status' => WhatsappStatus::Failed, 'error' => 'dispatch_failed']);
            Cache::forget("pw-pending:{$civitas->id}");
            throw $e;
        }

        AuditLogs::record('password.changed', $civitas, $req, [
            'wa_to' => $civitas->no_wa ? Phone::mask($civitas->no_wa) : null,
        ]);

        return new ChangeResult(ChangeOutcome::Queued);
    }
}