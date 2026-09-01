<?php

namespace App\Providers;

use App\Contracts\HospitalPatientProvider;
use App\Models\Cabinet;
use App\Models\Warehouse;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'warehouse' => Warehouse::class,
            'cabinet' => Cabinet::class,
        ]);
    }
}
