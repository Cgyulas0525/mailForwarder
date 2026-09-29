<?php

namespace Database\Seeders;

use App\Services\Import\LegacyUserImporter;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(LegacyUserImporter::class)->import();
        $this->command?->info('Felhasználóimport: '.$result->summary());
    }
}
