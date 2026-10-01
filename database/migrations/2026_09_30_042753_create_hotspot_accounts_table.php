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
        Schema::create('hotspot_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('civitas_id')->nullable()->unique()->constrained('civitas')->nullOnDelete();
            $table->string('username', 50)->unique();
            $table->string('mikrotik_id', 20)->nullable()->unique();
            $table->string('profile', 50)->nullable();
            $table->boolean('is_disabled')->default(false)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_reset_at')->nullable();
            $table->unsignedInteger('reset_count')->default(0);
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_accounts');
    }
};
