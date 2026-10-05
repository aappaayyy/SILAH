<?php
// tests/Feature/RegistrationTest.php
namespace Tests\Feature;

use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Honeypot\ProtectAgainstSpam;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function flow(): array
    {
        return ['register_flow' => [
            'identifier' => '2210513021', 'registration_id' => null,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]];
    }

    private function data(array $o = []): array
    {
        return array_merge([
            'nama' => 'Jafar Abdillah Sidik', 'tipe' => 'Mahasiswa', 'nim_nidn' => '2210513021',
            'no_wa' => '081912791085', 'email' => 'apay@mhs.test',
        ], $o);
    }

    public function test_mahasiswa_wajib_ktm(): void
    {
        $this->withoutMiddleware(ProtectAgainstSpam::class);

        $this->withSession($this->flow())
            ->post(route('register.store'), $this->data())
            ->assertSessionHasErrors('dokumen');
    }

    public function test_mahasiswa_dengan_ktm_tersimpan_di_disk_private(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(ProtectAgainstSpam::class);

        $this->withSession($this->flow())
            ->post(route('register.store'), $this->data(['dokumen' => UploadedFile::fake()->image('ktm.jpg')]))
            ->assertRedirect(route('home'));

        $reg = Registration::firstOrFail();
        $this->assertSame('6281912791085', $reg->no_wa);
        Storage::disk('local')->assertExists($reg->dokumen_path);
    }

    public function test_dosen_tanpa_dokumen_diterima_dan_berkas_diabaikan(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(ProtectAgainstSpam::class);

        $this->withSession($this->flow())
            ->post(route('register.store'), $this->data([
                'tipe' => 'Dosen', 'nim_nidn' => '0412038801',
                'dokumen' => UploadedFile::fake()->image('iseng.jpg'),
            ]))
            ->assertRedirect(route('home'));

        $this->assertNull(Registration::firstOrFail()->dokumen_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_duplikat_pending_ditolak(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(ProtectAgainstSpam::class);
        $payload = $this->data(['tipe' => 'Dosen']);

        $this->withSession($this->flow())->post(route('register.store'), $payload);
        $this->withSession($this->flow())->post(route('register.store'), $payload)
            ->assertSessionHasErrors('form');

        $this->assertSame(1, Registration::count());
    }

    public function test_tanpa_flow_cek_akun_ditolak(): void
    {
        $this->withoutMiddleware(ProtectAgainstSpam::class);

        $this->post(route('register.store'), $this->data(['tipe' => 'Dosen']))
            ->assertRedirect(route('home'));

        $this->assertSame(0, Registration::count());
    }
}