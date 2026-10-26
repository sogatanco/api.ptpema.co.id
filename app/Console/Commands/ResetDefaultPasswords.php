<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Accounts created from HR data used to get the same hardcoded password; this replaces it with a random one,
// after which the owner signs in through "Lupa Password". Dry run unless --apply is given.
class ResetDefaultPasswords extends Command
{
    protected $signature = 'security:reset-default-passwords {--apply : Actually change the passwords}';

    protected $description = 'Find accounts still using the old default password and give them a random one';

    public function handle(): int
    {
        $affected = User::all(['id', 'email', 'password'])
            ->filter(fn ($u) => $u->password && Hash::check('asdasdasd', $u->password));

        $this->info($affected->count() . ' akun masih memakai password bawaan.');

        if (!$this->option('apply')) {
            $this->line('Dry run: tidak ada yang diubah. Jalankan dengan --apply untuk mengganti password.');

            return self::SUCCESS;
        }

        foreach ($affected as $user) {
            $user->password = Hash::make(Str::random(40));
            $user->save();
        }
        $this->info('Password ' . $affected->count() . ' akun telah diganti. Pengguna masuk kembali lewat "Lupa Password".');

        return self::SUCCESS;
    }
}
