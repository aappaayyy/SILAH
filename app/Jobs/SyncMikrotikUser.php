<?php

namespace App\Jobs;

use App\Enums\WhatsappStatus;
use App\Models\AuditLogs;
use App\Models\HotspotAccount;
use App\Models\WhatsappMessage;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class SyncMikrotikUser implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 5;
    public array $backoff = [5, 15, 30, 60];
    public int $timeout = 20;
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $accountId,
        public int $messageId,
        public string $password,
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(MikrotikClient $mt): void
    {
        $account = HotspotAccount::findOrFail($this->accountId);

        $mt->upsertHotspotUser($account->username, $this->password, $account->profile);

        // Hash disimpan SETELAH router berhasil, jadi selalu cocok dengan password yang berlaku
        $account->forceFill([
            'password_hash'       => Hash::make($this->password),
            'password_changed_at' => now(),
        ])->save();
    }

    public function failed(Throwable $e): void
    {
        WhatsappMessage::whereKey($this->messageId)->update([
            'status' => WhatsappStatus::Failed,
            'error'  => 'sync_mikrotik: ' . Str::limit($e->getMessage(), 200, ''),
        ]);

        AuditLogs::record('hotspot.sync_failed', null, null, [
            'account_id' => $this->accountId,
            'reason'     => Str::limit($e->getMessage(), 100, ''),
        ]);
    }
}
