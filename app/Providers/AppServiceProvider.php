<?php

namespace App\Providers;

use App\Models\Cabinet;
use App\Models\Warehouse;
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
        Relation::enforceMorphMap([
            'warehouse' => Warehouse::class,
            'cabinet' => Cabinet::class,
        ]);
    }
}
