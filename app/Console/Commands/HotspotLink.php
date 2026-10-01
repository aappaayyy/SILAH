<?php

namespace App\Console\Commands;

use App\Models\Civitas;
use App\Models\HotspotAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:hotspot-link')]
#[Description('Command description')]
class HotspotLink extends Command
{

    protected $signature = 'hotspot:link';
    protected $description = 'Tautkan akun hotspot ke civitas berdasarkan username = nim_nidn';
    /**
     * Execute the console command.
     */
    public function handle() : int
    {
        $linked = 0;

        HotspotAccount::whereNull('civitas_id')->chunkById(500, function ($accounts) use (&$linked) {
            // username -> id civitas (satu query per potongan)
            $civitas = Civitas::whereIn('nim_nidn', $accounts->pluck('username'))
                ->get(['id', 'nim_nidn'])
                ->mapWithKeys(fn ($c) => [strtolower($c->nim_nidn) => $c->id]);

            // civitas yang sudah punya akun (civitas_id bersifat unik)
            $taken = HotspotAccount::whereIn('civitas_id', $civitas->values())
                ->pluck('civitas_id')
                ->flip();

            foreach ($accounts as $account) {
                $id = $civitas[$account->username] ?? null;

                if ($id && ! isset($taken[$id])) {
                    $account->update(['civitas_id' => $id]);
                    $taken[$id] = true;
                    $linked++;
                }
            }
        });

        $remaining = HotspotAccount::whereNull('civitas_id')->count();
        $this->info("Tertaut: {$linked} akun. Belum tertaut: {$remaining} akun.");

        return self::SUCCESS;
    }
}
