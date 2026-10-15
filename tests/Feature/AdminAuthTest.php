<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $reg = $this->pendaftaran();

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get(route('admin.registrations.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.registrations.show', $reg))->assertRedirect(route('admin.login'));
        $this->get(route('admin.whatsapp.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.registrations.approve', $reg), ['verified' => 1])->assertRedirect(route('admin.login'));
        $this->post(route('admin.registrations.reject', $reg), ['alasan_tolak' => 'Tidak sesuai'])->assertRedirect(route('admin.login'));
    }

    public function test_halaman_login_tampil(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Masuk Admin');
    }

    public function test_login_berhasil_mencatat_waktu_dan_audit(): void
    {
        $admin = $this->admin();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login']);
    }

    public function test_password_salah_ditolak_dan_dicatat(): void
    {
        $admin = $this->admin();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login_failed']);
    }

    public function test_admin_nonaktif_tidak_bisa_login(): void
    {
        $admin = $this->admin(aktif: false);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_yang_dinonaktifkan_saat_sesi_berjalan_langsung_dikeluarkan(): void
    {
        $admin = $this->admin(aktif: false);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_login_dibatasi_setelah_5_percobaan_gagal(): void
    {
        $admin = $this->admin();

        foreach (range(1, 5) as $_) {
            $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'salah']);
        }

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertStatus(429);

        $this->assertGuest();
    }

    public function test_admin_yang_sudah_login_tidak_melihat_form_login(): void
    {
        $this->actingAs($this->admin())->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_dashboard_menampilkan_jumlah_pending(): void
    {
        $this->pendaftaran();
        $this->pendaftaran(['nim_nidn' => '0412038802', 'no_wa' => '081200000010']);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pendaftaran menunggu')
            ->assertSee('>2<', false);
    }

    public function test_logout(): void
    {
        $this->actingAs($this->admin())->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }
}