<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\Civitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/**
 * Ganti password oleh pemilik akun dengan memasukkan password lama (jalan E).
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    /** Siapkan civitas + akun (dengan/tanpa hash) lalu lewati cek akun. */
    private function mulai(array $akun = [], ?string $hash = 'lama12345', array $civ = []): Civitas
    {
        Bus::fake();

        $c = $this->civitas($civ);
        $a = $this->akun($c, $akun);

        if ($hash) {
            $this->setHash($a, $hash);
        }

        $this->cekAkun($c->nim_nidn)->assertRedirect(route('home'));

        return $c;
    }

    private function ganti(string $lama, string $baru = 'baru12345', ?string $konfirmasi = null)
    {
        return $this->post(route('password.change'), [
            'password_lama'         => $lama,
            'password'              => $baru,
            'password_confirmation' => $konfirmasi ?? $baru,
        ]);
    }

    public function test_password_lama_benar_mengirim_chain_ke_router_lalu_wa(): void
    {
        $c = $this->mulai();

        $this->ganti('lama12345')->assertRedirect(route('home'))->assertSessionHasNoErrors();

        Bus::assertChained([SyncMikrotikUser::class, SendWhatsappMessage::class]);
        $this->assertSame('baru12345', Bus::dispatched(SyncMikrotikUser::class)->first()->password);
        $this->assertDatabaseHas('whatsapp_messages', ['to' => $c->no_wa, 'type' => 'password_diubah', 'status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.changed']);
    }

    public function test_hash_baru_hanya_diisi_job_setelah_router_berhasil(): void
    {
        $c = $this->mulai();

        $this->ganti('lama12345');

        // Job di-fake, jadi hash di database belum berubah
        $hash = $c->hotspotAccount->fresh()->password_hash;
        $this->assertTrue(Hash::check('lama12345', $hash));
        $this->assertFalse(Hash::check('baru12345', $hash));
    }

    public function test_pesan_wa_tidak_memuat_password(): void
    {
        $this->mulai();

        $this->ganti('lama12345');

        $wa = unserialize(Bus::dispatched(SyncMikrotikUser::class)->first()->chained[0]);
        $this->assertStringContainsString('diperbarui', $wa->text);
        $this->assertStringNotContainsString('baru12345', $wa->text);
        $this->assertStringNotContainsString('lama12345', $wa->text);
    }

    public function test_halaman_selesai_menjelaskan_password_sedang_diterapkan(): void
    {
        $this->mulai();

        $this->ganti('lama12345');

        $this->get(route('home'))->assertSee('Password baru sedang diterapkan');
    }

    public function test_password_lama_salah_menampilkan_sisa_percobaan(): void
    {
        $this->mulai();

        $this->ganti('salah-salah')->assertSessionHasErrors('password_lama');

        $this->assertStringContainsString('Sisa percobaan: 4', $this->pesanError('password_lama'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.change_failed']);
        Bus::assertNothingDispatched();
    }

    public function test_salah_lima_kali_mengunci_walau_percobaan_berikutnya_benar(): void
    {
        $this->mulai();

        foreach (range(1, 5) as $i) {
            $this->ganti('salah' . $i)->assertSessionHasErrors('password_lama');
        }

        $this->ganti('lama12345')->assertSessionHasErrors('password_lama');

        $this->assertStringContainsString('Terlalu banyak percobaan', $this->pesanError('password_lama'));
        Bus::assertNothingDispatched();
    }

    public function test_akun_tanpa_hash_tidak_bisa_ganti_sendiri(): void
    {
        $this->mulai(hash: null);

        $this->ganti('apapun123')->assertSessionHasErrors('password_lama');

        $this->assertStringContainsString('belum bisa memakai', $this->pesanError('password_lama'));
        Bus::assertNothingDispatched();
    }

    public function test_akun_dinonaktifkan_tidak_bisa_ganti(): void
    {
        $this->mulai(['is_disabled' => true]);

        $this->ganti('lama12345')->assertSessionHasErrors('password_lama');

        $this->assertStringContainsString('tidak dapat diubah', $this->pesanError('password_lama'));
        Bus::assertNothingDispatched();
    }

    public function test_permintaan_kedua_saat_yang_pertama_masih_diproses_ditolak(): void
    {
        $this->mulai();

        $this->ganti('lama12345')->assertSessionHasNoErrors();

        $this->cekAkun('2021001');
        $this->ganti('lama12345', 'lain12345')->assertSessionHasErrors('password_lama');

        $this->assertStringContainsString('masih diproses', $this->pesanError('password_lama'));
        Bus::assertDispatchedTimes(SyncMikrotikUser::class, 1);
    }

    public function test_password_lemah_ditolak_tanpa_menghabiskan_percobaan(): void
    {
        $this->mulai();

        $this->ganti('lama12345', 'hurufsaja')->assertSessionHasErrors('password');
        $this->ganti('lama12345', 'abc123')->assertSessionHasErrors('password');   // < 8 karakter

        Bus::assertNothingDispatched();
    }

    public function test_konfirmasi_harus_sama(): void
    {
        $this->mulai();

        $this->ganti('lama12345', 'baru12345', 'beda12345')->assertSessionHasErrors('password');

        Bus::assertNothingDispatched();
    }

    public function test_password_baru_harus_berbeda_dari_yang_lama(): void
    {
        $this->mulai();

        $this->ganti('lama12345', 'lama12345')->assertSessionHasErrors('password');

        Bus::assertNothingDispatched();
    }

    public function test_password_tidak_boleh_sama_dengan_username(): void
    {
        $this->mulai(civ: ['nim_nidn' => 'budisantoso1']);

        $this->ganti('lama12345', 'BudiSantoso1')->assertSessionHasErrors('password');

        $this->assertStringContainsString('sama dengan username', $this->pesanError('password'));
        Bus::assertNothingDispatched();
    }

    public function test_civitas_tanpa_wa_tetap_bisa_ganti_tanpa_pesan(): void
    {
        $this->mulai(civ: ['no_wa' => null]);

        $this->ganti('lama12345')->assertRedirect(route('home'))->assertSessionHasNoErrors();

        Bus::assertDispatched(SyncMikrotikUser::class);
        Bus::assertNotDispatched(SendWhatsappMessage::class);
    }

    public function test_tanpa_cek_akun_ditolak(): void
    {
        Bus::fake();

        $this->ganti('lama12345')->assertSessionHasErrors('identifier');

        Bus::assertNothingDispatched();
    }
}