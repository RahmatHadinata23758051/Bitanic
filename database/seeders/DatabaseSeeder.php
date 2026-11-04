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
        $this->command->call('bitanic:import-legacy', [
            '--dev-passwords' => true,
        ]);
    }
}
