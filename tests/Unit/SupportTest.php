<?php

namespace Tests\Unit;

use App\Services\Hotspot\Credentials;
use App\Support\Mask;
use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public static function nomor(): array
    {
        return [
            'awalan 0'        => ['081234567890', '6281234567890'],
            'tanda hubung'    => ['0812-3456-7890', '6281234567890'],
            'plus 62 + spasi' => ['+62 812 3456 7890', '6281234567890'],
            'tanpa awalan'    => ['81234567890', '6281234567890'],
            'sudah 62'        => ['6281234567890', '6281234567890'],
        ];
    }

    #[DataProvider('nomor')]
    public function test_phone_normalize(string $masuk, string $keluar): void
    {
        $this->assertSame($keluar, Phone::normalize($masuk));
    }

    public function test_phone_mask_menyembunyikan_bagian_tengah(): void
    {
        $this->assertSame('6281****890', Phone::mask('6281234567890'));
    }

    public function test_mask_nama(): void
    {
        $this->assertSame('Budi S*** W***', Mask::name('Budi Santoso Wijaya'));
        $this->assertSame('Budi', Mask::name('Budi'));
        $this->assertSame('Budi S***', Mask::name("  Budi   Santoso  "));
        $this->assertSame('Ünal Ç***', Mask::name('Ünal Çelik'));
    }

    public function test_password_acak_tanpa_karakter_ambigu(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression('/^[a-hj-km-np-z2-9]{10}$/', Credentials::password());
        }

        $this->assertSame(16, strlen(Credentials::password(16)));
    }

    public function test_password_acak_tidak_berulang(): void
    {
        $semua = array_map(fn () => Credentials::password(), range(1, 200));

        $this->assertCount(200, array_unique($semua));
    }

    public function test_pesan_akun_memuat_username_dan_password(): void
    {
        $pesan = Credentials::message('2021001', 'abc23xyz45');

        $this->assertStringContainsString('2021001', $pesan);
        $this->assertStringContainsString('abc23xyz45', $pesan);
        $this->assertStringContainsString('Jangan bagikan', $pesan);
    }

    public function test_pesan_akun_baru_menyebut_persetujuan(): void
    {
        $this->assertStringContainsString('disetujui', Credentials::message('u', 'p', true));
    }

    public function test_pesan_ganti_password_tidak_memuat_password(): void
    {
        $pesan = Credentials::changed('2021001');

        $this->assertStringContainsString('2021001', $pesan);
        $this->assertStringContainsString('diperbarui', $pesan);
        $this->assertStringNotContainsStringIgnoringCase('password:', $pesan);
    }
}