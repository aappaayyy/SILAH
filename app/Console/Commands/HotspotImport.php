<?php

namespace App\Console\Commands;

use App\Models\HotspotAccount;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Console\Command;

class HotspotImport extends Command
{
    /**
     * Signature / Nama Perintah Artisan
     */
    protected $signature = 'hotspot:import {--link : Jalankan hotspot:link setelah impor}';

    /**
     * Deskripsi Perintah
     */
    protected $description = 'Impor user hotspot dari MikroTik ke tabel hotspot_accounts';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikClient $mt): int
    {
        $startedAt = now();
        $ignore    = config('mikrotik.ignore_users', []);

        try {
            $users = $mt->getHotspotUsers(); // Sesuaikan dengan nama method di MikrotikClient
        } catch (\Throwable $e) {
            $this->error('Gagal terhubung ke MikroTik: ' . $e->getMessage());
            return self::FAILURE;
        }

        $now = now()->toDateTimeString();

        $rows = collect($users)
            ->reject(fn ($u) => blank($u['name'] ?? null) || in_array($u['name'], $ignore, true))
            ->map(fn ($u) => [
                'username'     => strtolower(trim($u['name'])),
                'mikrotik_id'  => $u['.id'] ?? null,
                'profile'      => $u['profile'] ?? null,
                'is_disabled'  => ($u['disabled'] ?? 'false') === 'true',
                'last_seen_at' => $startedAt,
                'created_at'   => $now,
                'updated_at'   => $now,
            ])
            ->unique('username')
            ->values();

        foreach ($rows->chunk(500) as $chunk) {
            HotspotAccount::upsert(
                $chunk->all(),
                ['username'],
                ['mikrotik_id', 'profile', 'is_disabled', 'last_seen_at', 'updated_at']
            );
        }

        // Akun yang ada di DB tetapi tidak terdeteksi lagi di router
        $missing = HotspotAccount::where(fn ($q) => $q
            ->whereNull('last_seen_at')
            ->orWhere('last_seen_at', '<', $startedAt))->count();

        $this->info("Diimpor: {$rows->count()} akun. Tidak terlihat di router: {$missing} akun.");

        // Jika opsi --link diberikan, panggil command hotspot:link
        if ($this->option('link')) {
            return $this->call('hotspot:link');
        }

        return self::SUCCESS;
    }
}
