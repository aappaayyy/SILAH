<?php

namespace Tests\Feature;

use App\Models\Civitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class CivitasModelTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    public function test_nomor_wa_dan_email_dinormalisasi_saat_disimpan(): void
    {
        $c = $this->civitas(['no_wa' => '0812-3456-7890', 'email' => '  BUDI@Kampus.AC.ID ']);

        $this->assertSame('6281234567890', $c->fresh()->no_wa);
        $this->assertSame('budi@kampus.ac.id', $c->fresh()->email);
    }

    public function test_find_by_identifier_untuk_semua_jenis_input(): void
    {
        $c = $this->civitas(['email' => 'budi@kampus.ac.id']);

        foreach (['2021001', ' 2021001 ', 'budi@kampus.ac.id', 'BUDI@KAMPUS.AC.ID', '081234567890', '+6281234567890'] as $input) {
            $this->assertTrue($c->is(Civitas::findByIdentifier($input)), "Gagal untuk input: {$input}");
        }
    }

    public function test_nim_berawalan_nol_tidak_tertukar_dengan_nomor_hp(): void
    {
        $c = $this->civitas(['nim_nidn' => '0412038801', 'no_wa' => '081200000001']);

        $this->assertTrue($c->is(Civitas::findByIdentifier('0412038801')));
    }

    public function test_civitas_nonaktif_dan_tidak_dikenal_tidak_ditemukan(): void
    {
        $this->civitas(['is_active' => false]);

        $this->assertNull(Civitas::findByIdentifier('2021001'));
        $this->assertNull(Civitas::findByIdentifier('9999999'));
        $this->assertNull(Civitas::findByIdentifier('tidak@ada.test'));
    }

    public function test_data_tersamar(): void
    {
        $c = $this->civitas();

        $this->assertSame('Budi S***', $c->nama_tersamar);
        $this->assertSame('6281****890', $c->wa_tersamar);
    }

    public function test_wa_tersamar_kosong_jika_tidak_ada_nomor(): void
    {
        $c = $this->civitas(['no_wa' => null]);

        $this->assertNull($c->wa_tersamar);
    }

    public function test_scope_aktif(): void
    {
        $this->civitas();
        $this->civitas(['nim_nidn' => '2021002', 'no_wa' => '081200000002', 'is_active' => false]);

        $this->assertSame(1, Civitas::aktif()->count());
    }
}