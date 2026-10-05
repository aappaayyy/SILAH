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

        // Idempotent: aman diulang saat retry
        $mt->upsertHotspotUser($account->username, $this->password, $account->profile);
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
