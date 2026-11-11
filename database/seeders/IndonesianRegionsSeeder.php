<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class IndonesianRegionsSeeder extends Seeder
{
    /**
     * Run the database seeds for Indonesian administrative regions.
     */
    public function run(): void
    {
        $this->command->info('Seeding Indonesian administrative regions reference data...');
        Artisan::call('bitanic:sync-indonesian-regions');
    }
}
