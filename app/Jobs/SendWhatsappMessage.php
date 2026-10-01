<?php

namespace App\Jobs;

use App\Enums\WhatsappStatus;
use App\Exceptions\WhatsappPermanentException;
use App\Models\AuditLogs;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class SendWhatsappMessage implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 4;
    public array $backoff = [10, 30, 90];
    public int $timeout = 45;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $messageId, public string $text)
    {

    }

    /**
     * Execute the job.
     */
    public function handle(WhatsappClient $wa): void
    {
        $msg = WhatsappMessage::find($this->messageId);

        // Sudah terkirim (mis. job dijalankan ulang) -> jangan kirim dobel
        if (! $msg || $msg->status === WhatsappStatus::Sent) {
            return;
        }

        $msg->increment('attempts');

        try {
            $wa->send($msg->uuid, $msg->to, $this->text);
        } catch (WhatsappPermanentException $e) {
            $this->fail($e);   // langsung gagal, tanpa retry; memanggil failed()
            return;
        }

        $msg->update(['status' => WhatsappStatus::Sent, 'sent_at' => now(), 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        WhatsappMessage::whereKey($this->messageId)->update([
            'status' => WhatsappStatus::Failed,
            'error'  => Str::limit($e->getMessage(), 500, ''),
        ]);

        AuditLogs::record('whatsapp.failed', null, null, [
            'message_id' => $this->messageId,
            'reason'     => Str::limit($e->getMessage(), 100, ''),
        ]);
    }
}
