<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hotspot_accounts', function (Blueprint $t) {
            $t->string('password_hash')->nullable()->after('profile');
            $t->timestamp('password_changed_at')->nullable()->after('password_hash');
        });

        // Jika Anda sebelumnya menjalankan migrasi OTP, tambahkan 'otp' ke daftar ini
        DB::statement("ALTER TABLE whatsapp_messages MODIFY `type`
            ENUM('akun_reset','akun_baru','pendaftaran_ditolak','password_diubah') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotspot_accounts', fn (Blueprint $t) => $t->dropColumn(['password_hash', 'password_changed_at']));
        DB::statement("ALTER TABLE whatsapp_messages MODIFY `type`
            ENUM('akun_reset','akun_baru','pendaftaran_ditolak') NOT NULL");
    }
};
