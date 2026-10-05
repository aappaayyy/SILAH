<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\Registration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:registration-purge-docs')]
#[Description('Command description')]
class RegistrationPurgeDocs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle() : int
    {
        $count = 0;

        Registration::whereNotNull('dokumen_path')
            ->where('status', '!=', RequestStatus::Pending)
            ->where('reviewed_at', '<', now()->subDays(30))
            ->chunkById(200, function ($regs) use (&$count) {
                foreach ($regs as $r) {
                    Storage::disk('local')->delete($r->dokumen_path);
                    $r->update(['dokumen_path' => null]);
                    $count++;
                }
            });

        $this->info("Dokumen dihapus: {$count}");
        return self::SUCCESS;
    }
}
