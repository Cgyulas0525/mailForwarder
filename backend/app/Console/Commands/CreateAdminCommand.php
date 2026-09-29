<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email} {--name=Adminisztrátor} {--password=}';

    protected $description = 'Admin felhasználó létrehozása vagy jelszavának cseréje';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = (string) ($this->option('password') ?: Str::password(20));
        $generated = $this->option('password') === null;

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => $password, 'is_admin' => true]
        );

        $this->info('Admin kész: '.$user->email);
        if ($generated) {
            $this->line('Egyszeri jelszó (nem kerül naplóba, mentsd el): '.$password);
        }

        return self::SUCCESS;
    }
}
