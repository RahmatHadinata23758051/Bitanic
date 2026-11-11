<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Seeding Bitanic vocational LMS master data...');

        $this->call([
            IndonesianRegionsSeeder::class,
            SubscriptionPlanSeeder::class,
            VocationalLmsSeeder::class,
            ModulePracticumSeeder::class,
        ]);

        if (app()->environment('production')) {
            if (User::exists()) {
                $this->command->warn('Production environment dengan data pengguna aktif terdeteksi. Seeder legacy dilewati demi melindungi integritas produksi.');

                return;
            }
        }

        $params = [];
        if (! app()->environment('production')) {
            $params['--dev-passwords'] = true;
        }

        $legacyDump = dirname(base_path()).DIRECTORY_SEPARATOR.'dump_upiedge.sql';
        if (file_exists($legacyDump)) {
            $this->command->call('bitanic:import-legacy', $params);
        }
    }
}
