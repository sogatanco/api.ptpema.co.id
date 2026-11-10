<?php

namespace App\Providers;

use App\Services\Notifikasi\Peristiwa;
use Illuminate\Support\ServiceProvider;

class NotifikasiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Peristiwa::daftar();
    }
}
