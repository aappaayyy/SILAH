<?php

namespace App\Services\Whatsapp;

use App\Exceptions\WhatsappPermanentException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsappClient
{
    /** Kirim pesan. Melempar exception jika gagal. */
    public function send(string $uuid, string $to, string $text): void
    {
        $body = json_encode(compact('uuid', 'to', 'text'), JSON_UNESCAPED_UNICODE);

        $res = $this->http($body)->send('POST', '/send');

        // 422 = nomor tidak valid / tidak terdaftar di WhatsApp -> percuma diulang
        if ($res->status() === 422) {
            throw new WhatsappPermanentException($res->json('error', 'rejected'));
        }

        $res->throw();
    }

    /** @return array{state:string,pending:int} */
    public function health(): array
    {
        return $this->http('')->get('/health')->throw()->json();
    }

    /** @return array{state:string,pending:int,qr:?string,connectedAt:?string} */
    public function status(): array
    {
        return $this->http('', 4)->get('/status')->throw()->json();
    }

    public function resetSession(): void
    {
        $this->http('{}', 5)->send('POST', '/session/reset')->throw();
    }

    private function http(string $body, ?int $timeout = null): PendingRequest
    {
        $ts  = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, config('wa.secret'));

        $req = Http::baseUrl(config('wa.url'))
            ->connectTimeout(3)
            ->timeout($timeout ?? config('wa.timeout'))
            ->acceptJson()
            ->withHeaders(['X-Timestamp' => $ts, 'X-Signature' => $sig]);

        return $body === '' ? $req : $req->withBody($body, 'application/json');
    }
}
