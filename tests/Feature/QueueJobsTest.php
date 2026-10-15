<?php

namespace Tests\Feature;

use App\Enums\WhatsappStatus;
use App\Enums\WhatsappType;
use App\Exceptions\WhatsappPermanentException;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\WhatsappMessage;
use App\Services\Mikrotik\MikrotikClient;
use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/**
 * Job queue (sync MikroTik, kirim WA) dan klien gateway. Semua tanpa jaringan sungguhan.
 */
class QueueJobsTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    private function pesan(array $o = []): WhatsappMessage
    {
        $c = $this->civitas();
        $a = $this->akun($c);

        return $a->whatsappMessages()->create(array_merge([
            'to' => $c->no_wa, 'type' => WhatsappType::AkunReset,
        ], $o))->fresh();
    }

    // ---------- SyncMikrotikUser ----------

    public function test_sync_memperbarui_router_lalu_mengisi_hash(): void
    {
        $c = $this->civitas();
        $a = $this->akun($c);

        $mt = Mockery::mock(MikrotikClient::class);
        $mt->shouldReceive('upsertHotspotUser')->once()->with('2021001', 'rahasia123', 'civitas');

        (new SyncMikrotikUser($a->id, 0, 'rahasia123'))->handle($mt);

        $a->refresh();
        $this->assertTrue(Hash::check('rahasia123', $a->password_hash));
        $this->assertNotNull($a->password_changed_at);
    }

    public function test_sync_gagal_di_router_tidak_mengubah_hash(): void
    {
        $c = $this->civitas();
        $a = $this->akun($c);

        $mt = Mockery::mock(MikrotikClient::class);
        $mt->shouldReceive('upsertHotspotUser')->once()->andThrow(new RuntimeException('timeout'));

        try {
            (new SyncMikrotikUser($a->id, 0, 'rahasia123'))->handle($mt);
            $this->fail('Seharusnya melempar exception agar job di-retry');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertNull($a->fresh()->password_hash);
    }

    public function test_sync_gagal_permanen_menandai_pesan_gagal_dan_dicatat(): void
    {
        $c = $this->civitas();
        $a = $this->akun($c);
        $m = $a->whatsappMessages()->create(['to' => $c->no_wa, 'type' => WhatsappType::AkunReset])->fresh();

        (new SyncMikrotikUser($a->id, $m->id, 'x'))->failed(new RuntimeException('router mati'));

        $m->refresh();
        $this->assertSame(WhatsappStatus::Failed, $m->status);
        $this->assertStringStartsWith('sync_mikrotik:', $m->error);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hotspot.sync_failed']);
    }

    // ---------- SendWhatsappMessage ----------

    public function test_kirim_wa_berhasil_menandai_terkirim(): void
    {
        Http::fake(['wa.test/send' => Http::response(['status' => 'sent'])]);
        $m = $this->pesan();

        (new SendWhatsappMessage($m->id, 'Halo'))->handle(app(WhatsappClient::class));

        $m->refresh();
        $this->assertSame(WhatsappStatus::Sent, $m->status);
        $this->assertNotNull($m->sent_at);
        $this->assertSame(1, $m->attempts);

        Http::assertSent(fn (Request $r) => $r->url() === 'http://wa.test/send'
            && $r->data()['to'] === '6281234567890'
            && $r->data()['uuid'] === $m->uuid
            && $r->data()['text'] === 'Halo');
    }

    public function test_pesan_yang_sudah_terkirim_tidak_dikirim_ulang(): void
    {
        Http::fake(['wa.test/send' => Http::response(['status' => 'sent'])]);
        $m = $this->pesan(['status' => WhatsappStatus::Sent]);

        (new SendWhatsappMessage($m->id, 'Halo'))->handle(app(WhatsappClient::class));

        Http::assertNothingSent();
    }

    public function test_error_sementara_dilempar_agar_di_retry_dan_status_tetap_queued(): void
    {
        Http::fake(['wa.test/send' => Http::response(['error' => 'not_connected'], 503)]);
        $m = $this->pesan();

        try {
            (new SendWhatsappMessage($m->id, 'Halo'))->handle(app(WhatsappClient::class));
            $this->fail('Seharusnya melempar RequestException');
        } catch (RequestException) {
            // diharapkan
        }

        $m->refresh();
        $this->assertSame(WhatsappStatus::Queued, $m->status);
        $this->assertSame(1, $m->attempts);
    }

    /**
     * Nomor tidak terdaftar di WhatsApp = kegagalan permanen: langsung Failed, tanpa retry.
     * Dijalankan lewat dispatch_sync agar mekanisme fail()/failed() bekerja seperti di worker.
     * Gagal dengan "undefined method fail()" berarti job memakai Illuminate\Bus\Queueable,
     * bukan Illuminate\Foundation\Queue\Queueable.
     */
    public function test_nomor_tidak_terdaftar_langsung_gagal_permanen(): void
    {
        Http::fake(['wa.test/send' => Http::response(['error' => 'not_on_whatsapp'], 422)]);
        $m = $this->pesan();

        dispatch_sync(new SendWhatsappMessage($m->id, 'Halo'));

        $m->refresh();
        $this->assertSame(WhatsappStatus::Failed, $m->status);
        $this->assertSame('not_on_whatsapp', $m->error);
        $this->assertDatabaseHas('audit_logs', ['action' => 'whatsapp.failed']);
        Http::assertSentCount(1);
    }

    public function test_failed_callback_menandai_pesan_gagal(): void
    {
        $m = $this->pesan();

        (new SendWhatsappMessage($m->id, 'Halo'))->failed(new RuntimeException('gateway down'));

        $m->refresh();
        $this->assertSame(WhatsappStatus::Failed, $m->status);
        $this->assertStringContainsString('gateway down', $m->error);
    }

    // ---------- WhatsappClient ----------

    public function test_permintaan_kirim_ditandatangani_hmac_dengan_timestamp(): void
    {
        Http::fake(['wa.test/send' => Http::response(['status' => 'sent'])]);

        app(WhatsappClient::class)->send('11111111-2222-3333-4444-555555555555', '6281234567890', 'Halo é');

        Http::assertSent(function (Request $r) {
            $ts = $r->header('X-Timestamp')[0];

            return abs(time() - (int) $ts) < 5
                && hash_equals(
                    hash_hmac('sha256', $ts . '.' . $r->body(), config('wa.secret')),
                    $r->header('X-Signature')[0]
                );
        });
    }

    public function test_status_422_menjadi_exception_permanen(): void
    {
        Http::fake(['wa.test/send' => Http::response(['error' => 'bad_number'], 422)]);

        $this->expectException(WhatsappPermanentException::class);

        app(WhatsappClient::class)->send('11111111-2222-3333-4444-555555555555', '6281234567890', 'Halo');
    }

    public function test_status_5xx_menjadi_request_exception(): void
    {
        Http::fake(['wa.test/send' => Http::response('', 500)]);

        $this->expectException(RequestException::class);

        app(WhatsappClient::class)->send('11111111-2222-3333-4444-555555555555', '6281234567890', 'Halo');
    }

    public function test_health_ditandatangani_walau_tanpa_isi(): void
    {
        Http::fake(['wa.test/health' => Http::response(['state' => 'connected', 'pending' => 0])]);

        $hasil = app(WhatsappClient::class)->health();

        $this->assertSame('connected', $hasil['state']);
        Http::assertSent(function (Request $r) {
            $ts = $r->header('X-Timestamp')[0];

            return $r->body() === ''
                && hash_equals(hash_hmac('sha256', $ts . '.', config('wa.secret')), $r->header('X-Signature')[0]);
        });
    }
}