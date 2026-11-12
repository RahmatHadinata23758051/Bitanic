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
        $this->command->info('Seeding Bitanic reference master data...');

        // 1. Authoritative Reference Data: Always safe for production and staging
        $this->call([
            IndonesianRegionsSeeder::class,
            SubscriptionPlanSeeder::class,
            ModulePracticumSeeder::class,
        ]);

        // 2. Production & Staging Safety Gate: NEVER seed demo accounts, shared passwords, or simulated hardware
        if (app()->environment('production')) {
            $this->command->info('Environment produksi/staging terdeteksi. Hanya data master referensi yang disemai. Akun demo dan data tiruan dilewati.');

            if (User::exists()) {
                $this->command->warn('Production environment dengan data pengguna aktif terdeteksi. Seeder legacy dilewati demi melindungi integritas produksi.');
            }

            return;
        }

        if (app()->environment('staging')) {
            $this->command->info('Environment staging terdeteksi. Hanya data master referensi yang disemai. Akun demo dan data tiruan dilewati.');

            return;
        }

        // 3. Local Development & Testing Fixtures Only
        $this->command->info('Seeding development demo vocational fixtures...');
        $this->call([
            VocationalLmsSeeder::class,
        ]);

        $params = [];
        $legacyDump = dirname(base_path()).DIRECTORY_SEPARATOR.'dump_upiedge.sql';
        if (file_exists($legacyDump)) {
            $this->command->call('bitanic:import-legacy', $params);
        }
    }
}
