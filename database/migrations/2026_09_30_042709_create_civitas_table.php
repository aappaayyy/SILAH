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
        Schema::create('civitas', function (Blueprint $table) {
            $table->id();
            $table->string('nim_nidn', 30)->unique();
            $table->string('nama');
            $table->string('email')->nullable()->unique();
            $table->string('no_wa', 20)->nullable()->unique();
            $table->enum('tipe', ['Mahasiswa', 'Dosen', 'Tendik'])->default('Mahasiswa');
            $table->string('unit', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('civitas');
    }
};
