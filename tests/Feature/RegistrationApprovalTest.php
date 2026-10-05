<?php
// tests/Feature/RegistrationApprovalTest.php
namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SyncMikrotikUser;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function reg(array $o = []): Registration
    {
        return Registration::create(array_merge([
            'nim_nidn' => '0412038801', 'nama' => 'Dewi Lestari', 'no_wa' => '6281234567890',
            'tipe' => 'Dosen', 'status' => RequestStatus::Pending, 'ip' => '127.0.0.1',
        ], $o));
    }

    public function test_setujui_membuat_civitas_akun_dan_mengirim_chain(): void
    {
        Bus::fake();
        $admin = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $reg = $this->reg();

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $reg), ['verified' => '1'])
            ->assertRedirect(route('admin.registrations.index'));

        Bus::assertChained([SyncMikrotikUser::class, SendWhatsappMessage::class]);
        $this->assertDatabaseHas('civitas', ['nim_nidn' => '0412038801', 'no_wa' => '6281234567890']);
        $this->assertDatabaseHas('hotspot_accounts', ['username' => '0412038801']);
        $this->assertSame(RequestStatus::Approved, $reg->fresh()->status);
    }

    public function test_tidak_bisa_disetujui_dua_kali(): void
    {
        Bus::fake();
        $admin = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $reg = $this->reg();

        $this->actingAs($admin)->post(route('admin.registrations.approve', $reg), ['verified' => '1']);
        $this->actingAs($admin)->post(route('admin.registrations.approve', $reg), ['verified' => '1'])
            ->assertSessionHasErrors('form');

        $this->assertSame(1, \App\Models\Civitas::count());
    }

    public function test_tamu_ditolak_masuk_panel(): void
    {
        $this->get(route('admin.registrations.index'))->assertRedirect(route('admin.login'));
    }
}