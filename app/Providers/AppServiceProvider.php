<?php

namespace App\Providers;

use App\Models\Civitas;
use App\Models\HotspotAccount;
use App\Models\NumberChange;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
            'user'          => User::class,
        ]);

        RateLimiter::for('portal-check', fn (Request $r) => [
            Limit::perMinute(6)->by($r->ip()),
            Limit::perHour(30)->by($r->ip()),
        ]);

        RateLimiter::for('portal', fn (Request $r) => [
            Limit::perMinute(10)->by($r->ip()),
        ]);

        RateLimiter::for('admin-login', fn (Request $r) =>
        Limit::perMinute(5)->by($r->ip() . '|' . mb_strtolower((string) $r->input('email'))));
    }
}
