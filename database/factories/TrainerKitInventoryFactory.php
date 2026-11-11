<?php

namespace Database\Factories;

use App\Models\TrainerKitInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerKitInventory>
 */
class TrainerKitInventoryFactory extends Factory
{
    protected $model = TrainerKitInventory::class;

    public function definition(): array
    {
        static $number = 1000;
        $number++;

        return [
            'serial_number' => 'BTK-2026-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'hardware_revision' => 'rev1.2',
            'batch_number' => 'BATCH-2026-Q1',
            'status' => TrainerKitInventory::STATUS_REGISTERED,
            'school_id' => null,
            'device_id' => null,
            'registered_at' => now(),
            'registered_by' => null,
            'notes' => 'Pabrikasi standar Bitanic.',
        ];
    }
}
