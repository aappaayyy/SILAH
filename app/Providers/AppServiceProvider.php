<?php

namespace App\Providers;

use App\Models\Civitas;
use App\Models\HotspotAccount;
use App\Models\NumberChange;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

    // Nama morph pendek supaya kolom *_type di DB rapi & tidak bergantung nama class
        Relation::enforceMorphMap([
            'civitas'       => Civitas::class,
            'hotspot'       => HotspotAccount::class,
            'registration'  => Registration::class,
            'number_change' => NumberChange::class,
        ]);
    }
}
