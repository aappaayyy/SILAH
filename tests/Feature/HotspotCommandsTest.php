<?php

namespace Tests\Feature;

use App\Models\HotspotAccount;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/**
 * Perintah artisan hotspot:import dan hotspot:link.
 */
class HotspotCommandsTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    private function router(array $users): void
    {
        $this->mock(MikrotikClient::class, fn (MockInterface $m) => $m->shouldReceive('listHotspotUsers')->once()->andReturn($users));
    }

    private function u(string $id, string $name, string $profile = 'civitas', string $disabled = 'false'): array
    {
        return ['.id' => $id, 'name' => $name, 'profile' => $profile, 'disabled' => $disabled];
    }

    // ---------- hotspot:import ----------

    public function test_import_membuat_akun_baru_dengan_username_huruf_kecil(): void
    {
        $this->router([$this->u('*1', 'Budi01', 'mhs', 'true')]);

        $this->artisan('hotspot:import')->assertSuccessful();

        $a = HotspotAccount::where('username', 'budi01')->firstOrFail();
        $this->assertSame('*1', $a->mikrotik_id);
        $this->assertSame('mhs', $a->profile);
        $this->assertTrue($a->is_disabled);
        $this->assertNull($a->civitas_id);
        $this->assertNotNull($a->last_seen_at);
    }

    public function test_import_tidak_menimpa_tautan_civitas_dan_riwayat_reset(): void
    {
        $c = $this->civitas();
        $a = $this->akun($c, ['reset_count' => 4, 'last_reset_at' => now()->subDay(), 'profile' => 'lama']);

        $this->router([$this->u('*7', '2021001', 'baru')]);
        $this->artisan('hotspot:import')->assertSuccessful();

        $a->refresh();
        $this->assertSame('baru', $a->profile);
        $this->assertSame('*7', $a->mikrotik_id);
        $this->assertSame($c->id, $a->civitas_id);
        $this->assertSame(4, $a->reset_count);
        $this->assertSame(1, HotspotAccount::count());
    }

    public function test_import_melewati_user_bawaan_yang_diabaikan(): void
    {
        $this->router([$this->u('*1', 'default-trial'), $this->u('*2', '2021001')]);

        $this->artisan('hotspot:import')->assertSuccessful();

        $this->assertSame(['2021001'], HotspotAccount::pluck('username')->all());
    }

    public function test_import_melaporkan_akun_yang_hilang_dari_router_tanpa_menghapusnya(): void
    {
        $this->akun(null, ['username' => 'lama99', 'last_seen_at' => now()->subDay()]);
        $this->router([$this->u('*1', '2021001'), $this->u('*2', '2021002')]);

        $this->artisan('hotspot:import')
            ->expectsOutputToContain('Diimpor: 2 akun')
            ->expectsOutputToContain('Tidak terlihat di router: 1 akun')
            ->assertSuccessful();

        $this->assertDatabaseHas('hotspot_accounts', ['username' => 'lama99']);
    }

    public function test_import_gagal_dengan_pesan_jelas_jika_router_tidak_terjangkau(): void
    {
        $this->mock(MikrotikClient::class, fn (MockInterface $m) => $m->shouldReceive('listHotspotUsers')->andThrow(new RuntimeException('timeout')));

        $this->artisan('hotspot:import')
            ->expectsOutputToContain('Gagal terhubung ke MikroTik')
            ->assertFailed();

        $this->assertSame(0, HotspotAccount::count());
    }

    public function test_import_dengan_opsi_link_langsung_menautkan(): void
    {
        $c = $this->civitas();
        $this->router([$this->u('*1', '2021001')]);

        $this->artisan('hotspot:import --link')->assertSuccessful();

        $this->assertSame($c->id, HotspotAccount::where('username', '2021001')->value('civitas_id'));
    }

    // ---------- hotspot:link ----------

    public function test_link_menautkan_username_yang_cocok_dengan_nim(): void
    {
        $c = $this->civitas();
        $this->akun(null, ['username' => '2021001']);
        $this->akun(null, ['username' => 'tidak-ada-di-master']);

        $this->artisan('hotspot:link')
            ->expectsOutputToContain('Tertaut: 1 akun')
            ->expectsOutputToContain('Belum tertaut: 1 akun')
            ->assertSuccessful();

        $this->assertSame($c->id, HotspotAccount::where('username', '2021001')->value('civitas_id'));
        $this->assertNull(HotspotAccount::where('username', 'tidak-ada-di-master')->value('civitas_id'));
    }

    public function test_link_tidak_membuat_satu_civitas_punya_dua_akun(): void
    {
        $c = $this->civitas();
        $this->akun($c, ['username' => 'akun-utama']);
        $kedua = $this->akun(null, ['username' => '2021001']);

        $this->artisan('hotspot:link')->assertSuccessful();

        $this->assertNull($kedua->fresh()->civitas_id);
    }

    public function test_link_tidak_membedakan_huruf_besar_kecil(): void
    {
        $c = $this->civitas(['nim_nidn' => 'NIP-AB1', 'no_wa' => '081200000077']);
        $this->akun(null, ['username' => 'NIP-AB1']);

        $this->artisan('hotspot:link')->assertSuccessful();

        $this->assertSame($c->id, HotspotAccount::where('username', 'nip-ab1')->value('civitas_id'));
    }

    public function test_link_tidak_menyentuh_akun_yang_sudah_tertaut(): void
    {
        $a = $this->civitas();
        $b = $this->civitas(['nim_nidn' => '2021002', 'no_wa' => '081200000002']);
        $akun = $this->akun($a, ['username' => '2021002']);   // sengaja salah tautan, bukan tugas link untuk memperbaiki

        $this->artisan('hotspot:link')->assertSuccessful();

        $this->assertSame($a->id, $akun->fresh()->civitas_id);
        $this->assertNotSame($b->id, $akun->fresh()->civitas_id);
    }
}