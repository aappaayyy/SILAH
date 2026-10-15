<?php

namespace Tests\Feature;

use App\Enums\WhatsappStatus;
use App\Enums\WhatsappType;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\HotspotAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/**
 * Reset akun oleh pengguna terdaftar: password dibuat sistem, dikirim ke WA data master (tanpa OTP).
 */
class ResetFlowTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    /** Cek akun lalu tekan tombol "Kirim akun baru ke WhatsApp". */
    private function reset(string $nim = '2021001', array $extra = [])
    {
        $this->cekAkun($nim);

        return $this->post(route('reset.store'), $extra);
    }

    public function test_reset_berhasil_membuat_akun_pesan_dan_chain(): void
    {
        Bus::fake();
        $c = $this->civitas();

        $this->reset()->assertRedirect(route('home'));

        Bus::assertChained([SyncMikrotikUser::class, SendWhatsappMessage::class]);
        $this->assertDatabaseHas('hotspot_accounts', ['civitas_id' => $c->id, 'username' => '2021001', 'reset_count' => 1]);
        $this->assertDatabaseHas('whatsapp_messages', ['to' => '6281234567890', 'type' => 'akun_reset', 'status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hotspot.reset']);

        $this->get(route('home'))->assertSee('sedang dikirim')->assertSee('6281****890');
    }

    public function test_password_sama_di_router_dan_pesan_serta_tidak_masuk_database(): void
    {
        Bus::fake();
        $this->civitas();

        $this->reset();

        $sync = Bus::dispatched(SyncMikrotikUser::class)->first();
        $pw   = $sync->password;
        $this->assertMatchesRegularExpression('/^[a-hj-km-np-z2-9]{10}$/', $pw);

        $wa = unserialize($sync->chained[0]);
        $this->assertInstanceOf(SendWhatsappMessage::class, $wa);
        $this->assertStringContainsString($pw, $wa->text);
        $this->assertStringContainsString('2021001', $wa->text);

        foreach (['hotspot_accounts', 'whatsapp_messages', 'audit_logs'] as $tabel) {
            $this->assertStringNotContainsString($pw, json_encode(DB::table($tabel)->get()), "Password bocor di tabel {$tabel}");
        }
    }

    public function test_flow_hanya_sekali_pakai(): void
    {
        Bus::fake();
        $this->civitas();

        $this->reset();
        $this->post(route('reset.store'))->assertRedirect(route('home'))->assertSessionHasErrors('identifier');

        Bus::assertDispatchedTimes(SyncMikrotikUser::class, 1);
    }

    public function test_sesi_kedaluwarsa_ditolak(): void
    {
        Bus::fake();
        $c = $this->civitas();

        $this->withSession(['reset_flow' => ['civitas_id' => $c->id, 'expires_at' => now()->subMinute()->timestamp]])
            ->post(route('reset.store'))
            ->assertSessionHasErrors('identifier');

        $this->assertStringContainsString('Sesi berakhir', $this->pesanError('identifier'));
        Bus::assertNothingDispatched();
    }

    public function test_reset_tanpa_cek_akun_ditolak(): void
    {
        Bus::fake();
        $this->civitas();

        $this->post(route('reset.store'))->assertSessionHasErrors('identifier');

        Bus::assertNothingDispatched();
    }

    public function test_tujuan_whatsapp_selalu_dari_data_master_bukan_input(): void
    {
        Bus::fake();
        $this->civitas();

        $this->reset('2021001', ['no_wa' => '6289999999999', 'to' => '6289999999999']);

        $this->assertDatabaseHas('whatsapp_messages', ['to' => '6281234567890']);
        $this->assertDatabaseMissing('whatsapp_messages', ['to' => '6289999999999']);
    }

    public function test_reset_kedua_dalam_15_menit_ditolak_cooldown(): void
    {
        Bus::fake();
        $this->civitas();

        $this->reset();
        $this->reset()->assertSessionHasErrors('identifier');

        $this->assertStringContainsString('Akun sudah dikirim', $this->pesanError('identifier'));
        Bus::assertDispatchedTimes(SyncMikrotikUser::class, 1);
    }

    public function test_batas_harian_per_civitas(): void
    {
        Bus::fake();
        $c = $this->civitas();

        foreach (range(1, 10) as $_) {
            RateLimiter::hit("reset:{$c->id}", 86400);
        }

        $this->reset()->assertSessionHasErrors('identifier');

        $this->assertStringContainsString('harian', $this->pesanError('identifier'));
        Bus::assertNothingDispatched();
    }

    public function test_pengiriman_gagal_sebelumnya_boleh_dicoba_lagi_tanpa_terhitung(): void
    {
        Bus::fake();
        $c = $this->civitas();
        $a = $this->akun($c, ['last_reset_at' => now(), 'reset_count' => 1]);
        $a->whatsappMessages()->create([
            'to' => $c->no_wa, 'type' => WhatsappType::AkunReset, 'status' => WhatsappStatus::Failed,
        ]);

        // Batas harian sudah habis, tetapi akun ini dikecualikan karena WA terakhir gagal
        foreach (range(1, 10) as $_) {
            RateLimiter::hit("reset:{$c->id}", 86400);
        }

        $this->reset()->assertRedirect(route('home'))->assertSessionHasNoErrors();

        Bus::assertChained([SyncMikrotikUser::class, SendWhatsappMessage::class]);
        $this->assertSame(2, $a->fresh()->reset_count);
    }

    public function test_reset_setelah_lewat_cooldown_menaikkan_reset_count(): void
    {
        Bus::fake();
        $c = $this->civitas();
        $a = $this->akun($c, ['last_reset_at' => now()->subHour(), 'reset_count' => 2]);

        $this->reset()->assertSessionHasNoErrors();

        $this->assertSame(3, $a->fresh()->reset_count);
        $this->assertSame(1, HotspotAccount::count());
    }

    public function test_civitas_tanpa_nomor_wa_tidak_diproses(): void
    {
        Bus::fake();
        $this->civitas(['no_wa' => null]);

        $this->reset()->assertSessionHasErrors('identifier');

        $this->assertStringContainsString('belum terdaftar', $this->pesanError('identifier'));
        Bus::assertNothingDispatched();
    }

    public function test_akun_dinonaktifkan_tidak_bisa_reset_sendiri(): void
    {
        Bus::fake();
        $c = $this->civitas();
        $this->akun($c, ['is_disabled' => true]);

        $this->reset()->assertSessionHasErrors('identifier');

        $this->assertStringContainsString('tidak dapat direset', $this->pesanError('identifier'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'hotspot.reset_blocked']);
        Bus::assertNothingDispatched();
    }

    public function test_akun_kedaluwarsa_tidak_bisa_reset_sendiri(): void
    {
        Bus::fake();
        $c = $this->civitas();
        $this->akun($c, ['expired_at' => now()->subDay()]);

        $this->reset()->assertSessionHasErrors('identifier');

        Bus::assertNothingDispatched();
    }

    public function test_akun_hasil_impor_yang_belum_tertaut_ditautkan_bukan_diduplikasi(): void
    {
        Bus::fake();
        $c   = $this->civitas();
        $lama = $this->akun(null, ['username' => '2021001', 'mikrotik_id' => '*1A']);

        $this->reset()->assertSessionHasNoErrors();

        $this->assertSame(1, HotspotAccount::count());
        $this->assertSame($c->id, $lama->fresh()->civitas_id);
        Bus::assertChained([SyncMikrotikUser::class, SendWhatsappMessage::class]);
    }

    public function test_username_milik_civitas_lain_diblokir_dan_dicatat(): void
    {
        Bus::fake();
        $this->civitas();
        $lain = $this->civitas(['nim_nidn' => '2021999', 'no_wa' => '081200000099']);
        $this->akun($lain, ['username' => '2021001']);

        $this->reset()->assertSessionHasErrors('identifier');

        $this->assertDatabaseHas('audit_logs', ['action' => 'hotspot.reset_blocked']);
        Bus::assertNothingDispatched();
    }
}