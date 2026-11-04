<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed subscription plans for vocational agriculture LMS.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Siswa Praktikan (Starter)',
                'code' => 'plan-siswa-starter',
                'price' => 0.00,
                'duration_days' => 365,
                'features' => [
                    'ai_quota' => 15,
                    'modules_access' => true,
                    'live_telemetry' => true,
                    'lks_submission' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Guru Pembimbing (Pro Educator)',
                'code' => 'plan-guru-pro',
                'price' => 99000.00,
                'duration_days' => 30,
                'features' => [
                    'ai_quota' => 250,
                    'modules_access' => true,
                    'rubric_grading' => true,
                    'csv_export' => true,
                    'schedule_management' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Laboratorium SMK 4.0 (Lab Enterprise)',
                'code' => 'plan-lab-enterprise',
                'price' => 499000.00,
                'duration_days' => 180,
                'features' => [
                    'ai_quota' => 0, // 0 = unlimited
                    'multi_station_benches' => 6,
                    'actuator_automation' => true,
                    'priority_support' => true,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }
}
