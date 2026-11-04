<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Seeding Bitanic database from legacy dataset...');

        if (app()->environment('production')) {
            if (\App\Models\User::exists()) {
                $this->command->warn('Production environment dengan data pengguna aktif terdeteksi. Seeder legacy dilewati demi melindungi integritas produksi.');
                return;
            }
        }

        $params = [];
        if (!app()->environment('production')) {
            $params['--dev-passwords'] = true;
        }

        $this->command->call('bitanic:import-legacy', $params);
    }
}
