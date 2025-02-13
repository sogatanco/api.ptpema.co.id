<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeService extends Command
{
    // Perintah artisan yang akan digunakan
    protected $signature = 'make:service {name}';
    
    // Deskripsi perintah
    protected $description = 'Membuat service class baru di app/Services';

    public function handle()
    {
        $name = $this->argument('name');
        $path = app_path("Services/{$name}.php");

        // Cek apakah file service sudah ada
        if (file_exists($path)) {
            $this->error("Service {$name} sudah ada!");
            return false;
        }

        // Pastikan folder Services ada
        (new Filesystem)->ensureDirectoryExists(app_path('Services'));

        // Template default untuk service
        $content = <<<PHP
        <?php

        namespace App\Services;

        class {$name}
        {
            public function exampleMethod()
            {
                return "Hello from {$name}!";
            }
        }
        PHP;

        // Buat file service baru
        file_put_contents($path, $content);
        
        // Tampilkan pesan sukses
        $this->info("Service {$name} berhasil dibuat di app/Services/");
    }
}
