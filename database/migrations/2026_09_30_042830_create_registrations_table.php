<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('nim_nidn', 30)->index();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('no_wa', 20)->index();
            $table->enum('tipe', ['Mahasiswa', 'Dosen', 'Tendik'])->default('Mahasiswa');
            $table->string('unit', 100)->nullable();
            $table->string('dokumen_path')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('alasan_tolak')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('civitas_id')->nullable()->constrained('civitas')->nullOnDelete();
            $table->ipAddress('ip');
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
