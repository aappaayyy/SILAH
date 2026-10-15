<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/**
 * Dashboard status WhatsApp: QR hanya untuk superadmin.
 */
class WhatsappAdminTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    private function fakeStatus(array $o = []): void
    {
        Http::fake(['wa.test/status' => Http::response(array_merge([
            'state' => 'need_qr', 'pending' => 2, 'qr' => 'data:image/png;base64,AAAA', 'connectedAt' => null,
        ], $o))]);
    }

    public function test_halaman_untuk_superadmin_menampilkan_tombol_reset(): void
    {
        $this->actingAs($this->admin('superadmin'))->get(route('admin.whatsapp.index'))
            ->assertOk()->assertSee('WhatsApp Gateway')->assertSee('Reset sesi / ganti nomor');
    }

    public function test_halaman_untuk_operator_tanpa_tombol_reset(): void
    {
        $this->actingAs($this->admin('operator'))->get(route('admin.whatsapp.index'))
            ->assertOk()->assertSee('WhatsApp Gateway')->assertDontSee('Reset sesi / ganti nomor');
    }

    public function test_superadmin_menerima_qr_dan_respons_tidak_disimpan_cache(): void
    {
        $this->fakeStatus();

        $res = $this->actingAs($this->admin('superadmin'))->getJson(route('admin.whatsapp.status'));

        $res->assertOk()->assertJsonPath('state', 'need_qr')->assertJsonPath('qr', 'data:image/png;base64,AAAA');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
    }

    public function test_operator_tidak_pernah_menerima_qr(): void
    {
        $this->fakeStatus();

        $this->actingAs($this->admin('operator'))->getJson(route('admin.whatsapp.status'))
            ->assertOk()
            ->assertJsonPath('state', 'need_qr')
            ->assertJsonPath('qr', null)
            ->assertDontSee('AAAA', false);
    }

    public function test_hanya_kunci_yang_diizinkan_diteruskan_ke_browser(): void
    {
        $this->fakeStatus(['secret' => 'rahasia-internal', 'state' => 'connected', 'qr' => null]);

        $this->actingAs($this->admin('superadmin'))->getJson(route('admin.whatsapp.status'))
            ->assertJsonStructure(['state', 'pending', 'qr', 'connectedAt'])
            ->assertJsonMissing(['secret' => 'rahasia-internal']);
    }

    public function test_gateway_mati_ditampilkan_sebagai_offline(): void
    {
        Http::fake(['wa.test/*' => Http::response('', 500)]);

        $this->actingAs($this->admin('superadmin'))->getJson(route('admin.whatsapp.status'))
            ->assertOk()->assertJsonPath('state', 'offline')->assertJsonPath('qr', null);
    }

    public function test_permintaan_ke_gateway_ditandatangani(): void
    {
        $this->fakeStatus();

        $this->actingAs($this->admin('superadmin'))->getJson(route('admin.whatsapp.status'));

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Timestamp') && $r->hasHeader('X-Signature'));
    }

    public function test_tamu_tidak_bisa_membaca_status(): void
    {
        $this->getJson(route('admin.whatsapp.status'))->assertUnauthorized();
    }

    // ---------- Reset sesi ----------

    public function test_operator_tidak_boleh_mereset_sesi(): void
    {
        Http::fake();

        $this->actingAs($this->admin('operator'))->post(route('admin.whatsapp.reset'))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_superadmin_bisa_mereset_sesi_dan_dicatat(): void
    {
        Http::fake(['wa.test/session/reset' => Http::response(['status' => 'resetting'])]);

        $this->actingAs($this->admin('superadmin'))->post(route('admin.whatsapp.reset'))
            ->assertRedirect()->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/session/reset'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'whatsapp.session_reset']);
    }

    public function test_reset_ditolak_gateway_jika_antrean_belum_kosong(): void
    {
        Http::fake(['wa.test/session/reset' => Http::response(['error' => 'queue_not_empty'], 409)]);

        $this->actingAs($this->admin('superadmin'))->post(route('admin.whatsapp.reset'))
            ->assertSessionHasErrors('wa');

        $this->assertStringContainsString('antrean', $this->pesanError('wa'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'whatsapp.session_reset']);
    }

    public function test_reset_saat_gateway_menolak_menampilkan_pesan_umum(): void
    {
        Http::fake(['wa.test/session/reset' => Http::response('', 500)]);

        $this->actingAs($this->admin('superadmin'))->post(route('admin.whatsapp.reset'))
            ->assertSessionHasErrors('wa');
    }
}