<?php

namespace Tests\Concerns;

use App\Enums\RequestStatus;
use App\Models\Civitas;
use App\Models\HotspotAccount;
use App\Models\Registration;
use App\Models\User;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Pembantu bersama untuk test fitur SILAH.
 * setUpMembuatData() dipanggil otomatis oleh Laravel (konvensi: setUp + nama trait).
 */
trait MembuatData
{
    protected function setUpMembuatData(): void
    {
        config([
            'honeypot.enabled' => false,
            'wa.url'           => 'http://wa.test',
            'wa.secret'        => str_repeat('s', 40),
        ]);

        // Pengaman: test tidak boleh menyentuh gateway WA maupun router sungguhan.
        // Request HTTP yang tidak di-fake akan error, dan panggilan MikroTik tanpa mock juga error.
        Http::preventStrayRequests();
        $this->mock(MikrotikClient::class);
    }

    protected function civitas(array $o = []): Civitas
    {
        return Civitas::create(array_merge([
            'nim_nidn' => '2021001',
            'nama'     => 'Budi Santoso',
            'no_wa'    => '081234567890',
            'tipe'     => 'Mahasiswa',
            'unit'     => 'Teknik Informatika',
        ], $o));
    }

    protected function akun(?Civitas $civitas = null, array $o = []): HotspotAccount
    {
        return HotspotAccount::create(array_merge([
            'civitas_id' => $civitas?->id,
            'username'   => $civitas?->nim_nidn ?? 'akun-lepas',
            'profile'    => 'civitas',
        ], $o));
    }

    /** password_hash sengaja tidak fillable (hanya job yang boleh mengisinya), jadi pakai forceFill. */
    protected function setHash(HotspotAccount $akun, string $password): HotspotAccount
    {
        $akun->forceFill(['password_hash' => Hash::make($password)])->save();

        return $akun;
    }

    protected function pendaftaran(array $o = []): Registration
    {
        return Registration::create(array_merge([
            'nim_nidn' => '0412038801',
            'nama'     => 'Dewi Lestari',
            'no_wa'    => '081298765432',
            'tipe'     => 'Dosen',
            'status'   => RequestStatus::Pending,
            'ip'       => '127.0.0.1',
        ], $o));
    }

    protected function admin(string $role = 'operator', bool $aktif = true): User
    {
        return User::factory()->create([
            'role'      => $role,
            'is_active' => $aktif,
            'password'  => 'rahasia123',
        ]);
    }

    protected function cekAkun(string $identifier): TestResponse
    {
        return $this->post(route('check'), ['identifier' => $identifier]);
    }

    /** Session untuk langkah "daftar" (setelah cek akun yang tidak ditemukan). */
    protected function flowDaftar(string $identifier = '2210513021', ?int $registrationId = null): array
    {
        return ['register_flow' => [
            'identifier'      => $identifier,
            'registration_id' => $registrationId,
            'expires_at'      => now()->addMinutes(30)->timestamp,
        ]];
    }

    /** Pesan error pertama untuk satu kunci, dari response terakhir. */
    protected function pesanError(string $key): string
    {
        return (string) (session('errors')?->first($key) ?? '');
    }
}