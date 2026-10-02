<?php

namespace App\Providers;

use App\Contracts\HospitalNurseProvider;
use App\Contracts\HospitalPatientProvider;
use App\Models\Cabinet;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\HospitalApiNurseProvider;
use App\Services\HospitalApiPatientProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HospitalPatientProvider::class, HospitalApiPatientProvider::class);
        $this->app->bind(HospitalNurseProvider::class, HospitalApiNurseProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'warehouse' => Warehouse::class,
            'user' => User::class,
            'cabinet' => Cabinet::class,
        ]);
    }
}
