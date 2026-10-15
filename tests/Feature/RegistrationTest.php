<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/**
 * Pendaftaran publik. KTM wajib hanya untuk mahasiswa; dosen/tendik tanpa dokumen.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    private function data(array $o = []): array
    {
        return array_merge([
            'nama'     => 'Rahmat Hidayat',
            'tipe'     => 'Mahasiswa',
            'nim_nidn' => '2210513021',
            'no_wa'    => '081234567890',
            'email'    => 'rahmat@mhs.test',
            'unit'     => 'Teknik Informatika',
        ], $o);
    }

    private function dosen(array $o = []): array
    {
        return $this->data(array_merge(['tipe' => 'Dosen', 'nim_nidn' => '0412038801'], $o));
    }

    private function kirim(array $data)
    {
        return $this->withSession($this->flowDaftar())->post(route('register.store'), $data);
    }

    public function test_mahasiswa_wajib_mengunggah_ktm(): void
    {
        $this->kirim($this->data())->assertSessionHasErrors('dokumen');

        $this->assertSame(0, Registration::count());
    }

    public function test_ktm_lebih_dari_2mb_ditolak(): void
    {
        Storage::fake('local');

        $this->kirim($this->data(['dokumen' => UploadedFile::fake()->create('ktm.pdf', 3000, 'application/pdf')]))
            ->assertSessionHasErrors('dokumen');
    }

    public function test_berkas_berbahaya_ditolak(): void
    {
        Storage::fake('local');

        $this->kirim($this->data(['dokumen' => UploadedFile::fake()->create('virus.exe', 50, 'application/x-msdownload')]))
            ->assertSessionHasErrors('dokumen');
        $this->kirim($this->data(['dokumen' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php')]))
            ->assertSessionHasErrors('dokumen');

        $this->assertSame(0, Registration::count());
    }

    public function test_mahasiswa_dengan_ktm_tersimpan_di_disk_private_dengan_nama_acak(): void
    {
        Storage::fake('local');

        $this->kirim($this->data(['dokumen' => UploadedFile::fake()->create('ktm-asli-saya.jpg', 100, 'image/jpeg')]))
            ->assertRedirect(route('home'));

        $reg = Registration::firstOrFail();
        $this->assertStringStartsWith('registrations/', $reg->dokumen_path);
        $this->assertStringNotContainsString('ktm-asli-saya', $reg->dokumen_path);
        Storage::disk('local')->assertExists($reg->dokumen_path);
    }

    public function test_data_tersimpan_dinormalisasi_dan_berstatus_pending(): void
    {
        Storage::fake('local');

        $this->kirim($this->data([
            'no_wa'   => '0812-3456-7890',
            'email'   => 'RAHMAT@MHS.TEST',
            'dokumen' => UploadedFile::fake()->create('ktm.jpg', 100, 'image/jpeg'),
        ]));

        $this->assertDatabaseHas('registrations', [
            'nim_nidn' => '2210513021',
            'no_wa'    => '6281234567890',
            'email'    => 'rahmat@mhs.test',
            'status'   => 'Pending',
            'ip'       => '127.0.0.1',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.submitted']);
    }

    public function test_setelah_kirim_halaman_menampilkan_status_menunggu(): void
    {
        $this->kirim($this->dosen())->assertRedirect(route('home'));

        $this->get(route('home'))->assertSee('menunggu verifikasi admin')->assertSee('Rahmat H***');
    }

    public function test_dosen_tanpa_dokumen_diterima_dan_berkas_yang_dikirim_diabaikan(): void
    {
        Storage::fake('local');

        $this->kirim($this->dosen(['dokumen' => UploadedFile::fake()->create('iseng.jpg', 50, 'image/jpeg')]))
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        $this->assertNull(Registration::firstOrFail()->dokumen_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_tendik_tidak_perlu_dokumen(): void
    {
        $this->kirim($this->data(['tipe' => 'Tendik', 'nim_nidn' => '198503122010']))->assertSessionHasNoErrors();

        $this->assertSame(1, Registration::count());
    }

    public function test_duplikat_pending_ditolak(): void
    {
        $this->kirim($this->dosen())->assertSessionHasNoErrors();
        $this->kirim($this->dosen())->assertSessionHasErrors('form');

        $this->assertSame(1, Registration::count());
    }

    public function test_pengajuan_yang_pernah_ditolak_boleh_diajukan_ulang(): void
    {
        $this->pendaftaran([
            'nim_nidn' => '0412038801', 'no_wa' => '6281234567890',
            'status' => RequestStatus::Rejected, 'reviewed_at' => now(),
        ]);

        $this->kirim($this->dosen())->assertSessionHasNoErrors();

        $this->assertSame(2, Registration::count());
    }

    public function test_nim_yang_sudah_ada_di_data_master_ditolak(): void
    {
        $this->civitas(['nim_nidn' => '0412038801', 'no_wa' => '081200000555']);

        $this->kirim($this->dosen())->assertSessionHasErrors('form');

        $this->assertSame(0, Registration::count());
    }

    public function test_nomor_wa_yang_sudah_dipakai_civitas_lain_ditolak(): void
    {
        $this->civitas(['nim_nidn' => '1111111', 'no_wa' => '081234567890']);

        $this->kirim($this->dosen())->assertSessionHasErrors('form');
    }

    public function test_pendaftaran_tanpa_cek_akun_ditolak(): void
    {
        $this->post(route('register.store'), $this->dosen())
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('identifier');

        $this->assertSame(0, Registration::count());
    }

    public function test_validasi_field(): void
    {
        $this->kirim($this->dosen(['nama' => '<b>Budi</b>']))->assertSessionHasErrors('nama');
        $this->kirim($this->dosen(['no_wa' => 'abc']))->assertSessionHasErrors('no_wa');
        $this->kirim($this->dosen(['no_wa' => '0812']))->assertSessionHasErrors('no_wa');
        $this->kirim($this->dosen(['email' => 'bukan-email']))->assertSessionHasErrors('email');
        $this->kirim($this->dosen(['tipe' => 'Admin']))->assertSessionHasErrors('tipe');
        $this->kirim($this->dosen(['nim_nidn' => "12 34'; DROP"]))->assertSessionHasErrors('nim_nidn');

        $this->assertSame(0, Registration::count());
    }
}