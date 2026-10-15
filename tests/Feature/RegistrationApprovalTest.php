<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\Civitas;
use App\Models\HotspotAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/**
 * Panel admin: antrean, tinjau, setujui, tolak, dan akses dokumen KTM.
 */
class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    private function setujui($reg, $admin, array $extra = ['verified' => '1'])
    {
        return $this->actingAs($admin)->post(route('admin.registrations.approve', $reg), $extra);
    }

    // ---------- Persetujuan ----------

    public function test_setujui_membuat_civitas_akun_pesan_dan_chain(): void
    {
        Bus::fake();
        $admin = $this->admin();
        $reg   = $this->pendaftaran();

        $this->setujui($reg, $admin)->assertRedirect(route('admin.registrations.index'));

        $civitas = Civitas::where('nim_nidn', '0412038801')->firstOrFail();
        $this->assertSame('6281298765432', $civitas->no_wa);
        $this->assertTrue($civitas->is_active);

        $this->assertDatabaseHas('hotspot_accounts', ['civitas_id' => $civitas->id, 'username' => '0412038801']);
        $this->assertDatabaseHas('whatsapp_messages', ['to' => '6281298765432', 'type' => 'akun_baru', 'status' => 'queued']);
        $this->assertDatabaseHas('registrations', [
            'id' => $reg->id, 'status' => 'Approved', 'reviewed_by' => $admin->id, 'civitas_id' => $civitas->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.approved']);

        Bus::assertChained([SyncMikrotikUser::class, SendWhatsappMessage::class]);
    }

    public function test_password_akun_baru_acak_dan_tidak_masuk_database(): void
    {
        Bus::fake();
        $this->setujui($this->pendaftaran(), $this->admin());

        $pw = Bus::dispatched(SyncMikrotikUser::class)->first()->password;
        $this->assertMatchesRegularExpression('/^[a-hj-km-np-z2-9]{10}$/', $pw);

        foreach (['civitas', 'hotspot_accounts', 'whatsapp_messages', 'registrations', 'audit_logs'] as $tabel) {
            $this->assertStringNotContainsString($pw, json_encode(DB::table($tabel)->get()), "Password bocor di {$tabel}");
        }
    }

    public function test_setelah_disetujui_pemohon_masuk_ke_alur_reset_di_portal(): void
    {
        Bus::fake();
        $this->setujui($this->pendaftaran(), $this->admin());

        $this->cekAkun('0412038801');

        $this->get(route('home'))->assertSee('Kirim akun baru ke WhatsApp')->assertSee('Dewi L***');
    }

    public function test_akun_hotspot_hasil_impor_ditautkan_bukan_error(): void
    {
        Bus::fake();
        $lama = $this->akun(null, ['username' => '0412038801', 'mikrotik_id' => '*2B', 'profile' => 'dosen']);

        $this->setujui($this->pendaftaran(), $this->admin())->assertSessionHasNoErrors();

        $civitas = Civitas::where('nim_nidn', '0412038801')->firstOrFail();
        $this->assertSame(1, HotspotAccount::count());
        $this->assertSame($civitas->id, $lama->fresh()->civitas_id);
        $this->assertSame('dosen', $lama->fresh()->profile, 'Profil bandwidth lama tidak boleh berubah');

        $sync = Bus::dispatched(SyncMikrotikUser::class)->first();
        $this->assertSame($lama->id, $sync->accountId);
    }

    public function test_akun_hotspot_yang_sudah_tertaut_ke_civitas_lain_menghalangi_persetujuan(): void
    {
        Bus::fake();
        $lain = $this->civitas(['nim_nidn' => '2021999', 'no_wa' => '081200000099']);
        $this->akun($lain, ['username' => '0412038801']);
        $reg = $this->pendaftaran();

        $this->setujui($reg, $this->admin())->assertSessionHasErrors('form');

        $this->assertSame(RequestStatus::Pending, $reg->fresh()->status);
        $this->assertSame(0, Civitas::where('nim_nidn', '0412038801')->count());
        Bus::assertNothingDispatched();
    }

    public function test_data_yang_sudah_ada_di_master_menghalangi_persetujuan(): void
    {
        Bus::fake();
        $this->civitas(['nim_nidn' => '0412038801', 'no_wa' => '081200000555']);
        $reg = $this->pendaftaran();

        $this->setujui($reg, $this->admin())->assertSessionHasErrors('form');

        $this->assertSame(RequestStatus::Pending, $reg->fresh()->status);
        Bus::assertNothingDispatched();
    }

    public function test_konfirmasi_verifikasi_wajib_dicentang(): void
    {
        Bus::fake();
        $reg = $this->pendaftaran();

        $this->setujui($reg, $this->admin(), [])->assertSessionHasErrors('verified');

        $this->assertSame(RequestStatus::Pending, $reg->fresh()->status);
        Bus::assertNothingDispatched();
    }

    public function test_tidak_bisa_disetujui_dua_kali(): void
    {
        Bus::fake();
        $admin = $this->admin();
        $reg   = $this->pendaftaran();

        $this->setujui($reg, $admin)->assertSessionHasNoErrors();
        $this->setujui($reg, $admin)->assertSessionHasErrors('form');

        $this->assertSame(1, Civitas::count());
        Bus::assertDispatchedTimes(SyncMikrotikUser::class, 1);
    }

    // ---------- Penolakan ----------

    public function test_tolak_mengubah_status_dan_memberi_tahu_pemohon_lewat_wa(): void
    {
        Bus::fake();
        $admin = $this->admin();
        $reg   = $this->pendaftaran();

        $this->actingAs($admin)
            ->post(route('admin.registrations.reject', $reg), ['alasan_tolak' => 'Foto dokumen tidak terbaca'])
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertDatabaseHas('registrations', [
            'id' => $reg->id, 'status' => 'Rejected', 'alasan_tolak' => 'Foto dokumen tidak terbaca', 'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('whatsapp_messages', ['to' => '6281298765432', 'type' => 'pendaftaran_ditolak', 'status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.rejected']);
        $this->assertSame(0, Civitas::count());

        Bus::assertDispatched(SendWhatsappMessage::class);
        $this->assertStringContainsString('Foto dokumen tidak terbaca', Bus::dispatched(SendWhatsappMessage::class)->first()->text);
    }

    public function test_pemohon_melihat_alasan_penolakan_di_portal(): void
    {
        Bus::fake();
        $this->actingAs($this->admin())
            ->post(route('admin.registrations.reject', $this->pendaftaran()), ['alasan_tolak' => 'Nama tidak sesuai KTM']);

        $this->cekAkun('0412038801');

        $this->get(route('home'))->assertSee('Alasan: Nama tidak sesuai KTM')->assertSee('Ajukan ulang');
    }

    public function test_alasan_penolakan_wajib_dan_minimal_5_karakter(): void
    {
        Bus::fake();
        $reg = $this->pendaftaran();

        $this->actingAs($this->admin())->post(route('admin.registrations.reject', $reg), ['alasan_tolak' => ''])
            ->assertSessionHasErrors('alasan_tolak');
        $this->actingAs($this->admin())->post(route('admin.registrations.reject', $reg), ['alasan_tolak' => 'ab'])
            ->assertSessionHasErrors('alasan_tolak');

        $this->assertSame(RequestStatus::Pending, $reg->fresh()->status);
    }

    public function test_pengajuan_yang_sudah_diproses_tidak_bisa_ditolak(): void
    {
        Bus::fake();
        $admin = $this->admin();
        $reg   = $this->pendaftaran();

        $this->setujui($reg, $admin);
        $this->actingAs($admin)->post(route('admin.registrations.reject', $reg), ['alasan_tolak' => 'Terlambat menolak'])
            ->assertSessionHasErrors('form');

        $this->assertSame(RequestStatus::Approved, $reg->fresh()->status);
    }

    // ---------- Antrean dan tinjau ----------

    public function test_antrean_menampilkan_pending_secara_bawaan_dan_bisa_difilter(): void
    {
        $this->pendaftaran(['nama' => 'Dewi Lestari']);
        $this->pendaftaran(['nama' => 'Eko Prasetyo', 'nim_nidn' => '0412038802', 'no_wa' => '081200000010']);
        $this->pendaftaran([
            'nama' => 'Fajar Nugroho', 'nim_nidn' => '0412038803', 'no_wa' => '081200000011',
            'status' => RequestStatus::Approved, 'reviewed_at' => now(),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.registrations.index'))
            ->assertOk()->assertSee('Dewi Lestari')->assertSee('Eko Prasetyo')->assertDontSee('Fajar Nugroho');

        $this->actingAs($admin)->get(route('admin.registrations.index', ['status' => 'Approved']))
            ->assertSee('Fajar Nugroho')->assertDontSee('Dewi Lestari');

        // Status tidak dikenal jatuh ke Pending, bukan error
        $this->actingAs($admin)->get(route('admin.registrations.index', ['status' => 'ngawur']))
            ->assertOk()->assertSee('Dewi Lestari');
    }

    public function test_halaman_tinjau_menyesuaikan_teks_verifikasi_dengan_peran(): void
    {
        $dosen = $this->pendaftaran();
        $mhs   = $this->pendaftaran([
            'nim_nidn' => '2210513021', 'no_wa' => '081234567890', 'nama' => 'Rahmat Hidayat', 'tipe' => 'Mahasiswa',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.registrations.show', $dosen))
            ->assertOk()->assertSee('Tinjau Pengajuan')->assertSee('data kepegawaian');

        $this->actingAs($admin)->get(route('admin.registrations.show', $mhs))
            ->assertSee('data SIAKAD');
    }

    public function test_tinjau_memberi_peringatan_jika_data_sudah_ada_di_master(): void
    {
        $this->civitas(['nim_nidn' => '0412038801', 'no_wa' => '081200000555']);

        $this->actingAs($this->admin())->get(route('admin.registrations.show', $this->pendaftaran()))
            ->assertSee('tidak dapat disetujui');
    }

    /** Memerlukan penambahan variabel $akun pada show() (langkah akun hotspot yang sudah ada). */
    public function test_tinjau_memperingatkan_bahwa_akun_mikrotik_lama_akan_ditimpa(): void
    {
        $this->akun(null, ['username' => '0412038801']);

        $this->actingAs($this->admin())->get(route('admin.registrations.show', $this->pendaftaran()))
            ->assertSee('ditautkan ke pemohon dan passwordnya diganti');
    }

    // ---------- Dokumen KTM ----------

    public function test_dokumen_hanya_bisa_dibuka_admin_dan_dicatat(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('registrations/2026/10/ktm.jpg', 'isi-berkas');
        $reg = $this->pendaftaran(['tipe' => 'Mahasiswa', 'dokumen_path' => 'registrations/2026/10/ktm.jpg']);

        $this->get(route('admin.registrations.document', $reg))->assertRedirect(route('admin.login'));

        $res = $this->actingAs($this->admin())->get(route('admin.registrations.document', $reg));

        $res->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.document_viewed']);
    }

    public function test_dokumen_tidak_ada_menghasilkan_404(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())->get(route('admin.registrations.document', $this->pendaftaran()))->assertNotFound();

        $hilang = $this->pendaftaran([
            'nim_nidn' => '2210513021', 'no_wa' => '081234567890', 'dokumen_path' => 'registrations/tidak-ada.jpg',
        ]);
        $this->actingAs($this->admin())->get(route('admin.registrations.document', $hilang))->assertNotFound();
    }
}