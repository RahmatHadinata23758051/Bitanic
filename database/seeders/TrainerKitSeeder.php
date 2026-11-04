<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class TrainerKitSeeder extends Seeder
{
    /**
     * Seed 6 vocational trainer kit workstations for SMK lab.
     */
    public function run(): void
    {
        $kits = [
            [
                'code' => 'TK-01',
                'name' => 'Meja Praktik 1 — Stasiun Agronomi Dasar',
                'connection_type' => 'wifi',
                'is_active' => true,
                'note' => 'Stasiun trainer kit untuk modul K3 dan pengenalan sensor.',
            ],
            [
                'code' => 'TK-02',
                'name' => 'Meja Praktik 2 — Stasiun Kalibrasi pH & Buffer',
                'connection_type' => 'wifi',
                'is_active' => true,
                'note' => 'Dilengkapi larutan buffer kalibrasi pH 4.01 dan pH 6.86.',
            ],
            [
                'code' => 'TK-03',
                'name' => 'Meja Praktik 3 — Stasiun Lengas Tanah & Irigasi Drip',
                'connection_type' => 'wifi',
                'is_active' => true,
                'note' => 'Dilengkapi relai 12V dan solenoid valve irigasi tetes.',
            ],
            [
                'code' => 'TK-04',
                'name' => 'Meja Praktik 4 — Stasiun EC Nutrisi AB Mix',
                'connection_type' => 'wifi',
                'is_active' => true,
                'note' => 'Stasiun pengujian daya hantar listrik dan salinitas tanah.',
            ],
            [
                'code' => 'TK-05',
                'name' => 'Meja Praktik 5 — Stasiun Makronutrien N-P-K',
                'connection_type' => 'wifi',
                'is_active' => true,
                'note' => 'Stasiun pengujian defisiensi unsur hara makro tanaman.',
            ],
            [
                'code' => 'TK-06',
                'name' => 'Meja Praktik 6 — Stasiun Mikroklimat & Otomasi Fan',
                'connection_type' => 'wifi',
                'is_active' => true,
                'note' => 'Dilengkapi misting fan dan sensor suhu-kelembaban udara.',
            ],
        ];

        foreach ($kits as $kit) {
            Device::updateOrCreate(
                ['code' => $kit['code']],
                $kit
            );
        }
    }
}
