<?php

namespace App\Services\Mikrotik;

use RouterOS\Client;
use RouterOS\Query;

class MikrotikClient
{
    /**
     * Create a new class instance.
     */
    private ?Client $client = null;

    // Koneksi baru dibuka saat pertama dipakai (lazy)
    private function client(): Client
    {
        return $this->client ??= new Client([
            'host'     => config('routeros-api.host'),
            'user'     => config('routeros-api.user'),
            'pass'     => config('routeros-api.pass'),
            'port'     => (int) config('routeros-api.port', 18143),
            'ssl'      => filter_var(config('routeros-api.ssl', true), FILTER_VALIDATE_BOOL),
            'timeout'  => 3,
            'attempts' => 1,
        ]);
    }

    public function ping(): string
    {
        $r = $this->client()->query(new Query('/system/identity/print'))->read();
        return $r[0]['name'] ?? '';
    }

    /** Ambil semua user hotspot TANPA field password. */
    public function listHotspotUsers(): array
    {
        $q = (new Query('/ip/hotspot/user/print'))
            ->equal('.proplist', '.id,name,profile,disabled');

        return $this->client()->query($q)->read();
    }

    private function findId(string $username): ?string
    {
        $q = (new Query('/ip/hotspot/user/print'))
            ->where('name', $username)
            ->equal('.proplist', '.id');

        return $this->client()->query($q)->read()[0]['.id'] ?? null;
    }

    /**
     * Idempotent: aman diulang oleh retry job.
     * - Sudah ada  -> hanya ganti password (profil & status disabled tidak disentuh)
     * - Belum ada  -> buat baru dengan profil default
     */
    public function upsertHotspotUser(string $username, string $password, ?string $profile = null): void
    {
        $id = $this->findId($username);

        if ($id) {
            $this->client()->query(
                (new Query('/ip/hotspot/user/set'))
                    ->equal('.id', $id)
                    ->equal('password', $password)
            )->read();
            return;
        }

        $this->client()->query(
            (new Query('/ip/hotspot/user/add'))
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profile ?? config('mikrotik.default_profile'))
        )->read();
    }

    public function setDisabled(string $username, bool $disabled): void
    {
        if (! $id = $this->findId($username)) {
            return;
        }

        $this->client()->query(
            (new Query('/ip/hotspot/user/set'))
                ->equal('.id', $id)
                ->equal('disabled', $disabled ? 'yes' : 'no')
        )->read();
    }
}
