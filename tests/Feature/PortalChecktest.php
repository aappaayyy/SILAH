<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class PortalChecktest extends TestCase
{
    use RefreshDatabase, MembuatData;

    public function test_halaman_awal_menampilkan_form_cek_akun(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('name="identifier"', false);
    }

    public static function inputTerdaftar(): array
    {
        return [
            'nim'               => ['2021001'],
            'email'             => ['budi@kampus.ac.id'],
            'email huruf besar' => ['BUDI@KAMPUS.AC.ID'],
            'nomor hp'          => ['081234567890'],
        ];
    }

    #[DataProvider('inputTerdaftar')]
    public function test_akun_terdaftar_menampilkan_data_tersamar_saja(string $input): void
    {
        $this->civitas(['email' => 'budi@kampus.ac.id']);

        $this->cekAkun($input)->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Budi S***')
            ->assertSee('6281****890')
            ->assertSee('Kirim akun baru ke WhatsApp')
            ->assertDontSee('Budi Santoso')
            ->assertDontSee('6281234567890')
            ->assertDontSee('budi@kampus.ac.id');
    }

    public function test_panel_ganti_password_hanya_muncul_jika_akun_punya_hash(): void
    {
        $c = $this->civitas();
        $a = $this->akun($c);

        $this->cekAkun('2021001');
        $this->get(route('home'))->assertDontSee('Saya ingat password lama');

        $this->setHash($a, 'lama12345');
        $this->cekAkun('2021001');
        $this->get(route('home'))->assertSee('Saya ingat password lama');
    }

    public function test_nim_belum_terdaftar_menampilkan_form_daftar_dengan_isian_awal(): void
    {
        $this->cekAkun('2210999999')->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertSee('Kirim pendaftaran')
            ->assertSee('value="2210999999"', false);
    }

    public function test_email_dan_hp_yang_diketik_ikut_terisi_di_form_daftar(): void
    {
        $this->cekAkun('rahmat@kampus.test');
        $this->get(route('home'))->assertSee('value="rahmat@kampus.test"', false);

        $this->cekAkun('081211112222');
        $this->get(route('home'))->assertSee('value="081211112222"', false);
    }

    public function test_civitas_nonaktif_diperlakukan_sebagai_belum_terdaftar(): void
    {
        $this->civitas(['is_active' => false]);

        $this->cekAkun('2021001');

        $this->get(route('home'))->assertSee('Kirim pendaftaran')->assertDontSee('Budi S***');
    }

    public function test_pengajuan_pending_menampilkan_status_menunggu_tanpa_data_lengkap(): void
    {
        $this->pendaftaran(['nim_nidn' => '0412038802', 'nama' => 'Andi Pratama', 'no_wa' => '081200000010']);
        $this->pendaftaran();

        $this->cekAkun('0412038801');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('menunggu verifikasi admin')
            ->assertSee('Dewi L***')
            ->assertSee('ke-2')
            ->assertDontSee('Dewi Lestari')
            ->assertDontSee('6281298765432');
    }

    public function test_pengajuan_ditolak_dalam_7_hari_menampilkan_alasan_dan_tombol_ajukan_ulang(): void
    {
        $this->pendaftaran([
            'status'       => RequestStatus::Rejected,
            'alasan_tolak' => 'Foto KTM tidak terbaca',
            'reviewed_at'  => now()->subDays(2),
        ]);

        $this->cekAkun('0412038801');

        $this->get(route('home'))
            ->assertSee('ditolak')
            ->assertSee('Alasan: Foto KTM tidak terbaca')
            ->assertSee('Ajukan ulang');
    }

    public function test_pengajuan_ditolak_lebih_dari_7_hari_kembali_ke_form_daftar(): void
    {
        $this->pendaftaran(['status' => RequestStatus::Rejected, 'alasan_tolak' => 'Lama', 'reviewed_at' => now()->subDays(8)]);

        $this->cekAkun('0412038801');

        $this->get(route('home'))->assertSee('Kirim pendaftaran')->assertDontSee('Ajukan ulang');
    }

    public function test_ajukan_ulang_membuka_form_daftar(): void
    {
        $this->pendaftaran(['status' => RequestStatus::Rejected, 'alasan_tolak' => 'Salah', 'reviewed_at' => now()]);

        $this->cekAkun('0412038801');
        $this->post(route('portal.reapply'))->assertRedirect(route('home'));

        $this->get(route('home'))->assertSee('Kirim pendaftaran');
    }

    public function test_pengajuan_disetujui_tidak_tampil_sebagai_ditolak(): void
    {
        $reg = $this->pendaftaran(['status' => RequestStatus::Approved, 'reviewed_at' => now()]);

        $this->withSession($this->flowDaftar('0412038801', $reg->id))
            ->get(route('home'))
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertSee('sudah disetujui')
            ->assertDontSee('ditolak');
    }

    public function test_sesi_kedaluwarsa_kembali_ke_form_cek(): void
    {
        $c = $this->civitas();

        $this->withSession(['reset_flow' => ['civitas_id' => $c->id, 'expires_at' => now()->subMinute()->timestamp]])
            ->get(route('home'))
            ->assertSee('name="identifier"', false)
            ->assertDontSee('Budi S***');
    }

    public function test_tombol_cek_akun_lain_menghapus_flow(): void
    {
        $this->civitas();
        $this->cekAkun('2021001');

        $this->post(route('portal.cancel'))->assertRedirect(route('home'));

        $this->get(route('home'))->assertSee('name="identifier"', false)->assertDontSee('Budi S***');
    }

    public static function inputTidakValid(): array
    {
        return [
            'kosong'          => [''],
            'karakter aneh'   => ['2021 001!'],
            'tag html'        => ['<script>alert(1)</script>'],
            'terlalu panjang' => [str_repeat('1', 101)],
        ];
    }

    #[DataProvider('inputTidakValid')]
    public function test_input_tidak_valid_ditolak(string $input): void
    {
        $this->cekAkun($input)->assertSessionHasErrors('identifier');
    }

    public function test_cek_akun_dibatasi_per_menit(): void
    {
        config(['portal.limits.ip_check_per_minute' => 5]);

        $status = [];
        for ($i = 0; $i < 8; $i++) {
            $status[] = $this->cekAkun('99900' . $i)->status();
        }

        $this->assertContains(429, $status, 'Rate limit /cek-akun tidak aktif');
    }
}