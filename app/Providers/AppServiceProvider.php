<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
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
        // Shared hosting (InfinityFree/MariaDB) membatasi index key 1000 bytes.
        // varchar(255) utf8mb4 = 1020 bytes → gagal. 191 char = 764 bytes, aman.
        Schema::defaultStringLength(191);
    }
}
