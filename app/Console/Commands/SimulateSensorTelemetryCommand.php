<?php

namespace App\Console\Commands;

use App\Actions\Telemetry\IngestTelemetryAction;
use App\Models\Device;
use Illuminate\Console\Command;

class SimulateSensorTelemetryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bitanic:simulate-telemetry
                            {--count=20 : Jumlah data telemetri yang di-generate}
                            {--device= : Kode stasiun spesifik (misal TK-01)}
                            {--anomaly : Paksa injeksi deviasi anomali agronomi}';

    /**
     * The console command description.
     */
    protected $description = 'Simulasi stream data telemetri 7-in-1 Modbus untuk meja trainer kit dan uji stress testing';

    /**
     * Execute the console command.
     */
    public function handle(IngestTelemetryAction $action): int
    {
        $count = (int) $this->option('count');
        $deviceCode = $this->option('device');
        $forceAnomaly = (bool) $this->option('anomaly');

        $query = Device::where('is_active', true);
        if ($deviceCode) {
            $query->where('code', $deviceCode);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->error('Tidak ada meja trainer kit aktif yang ditemukan.');
            return self::FAILURE;
        }

        $this->info("Memulai simulasi telemetri {$count} paket pada {$devices->count()} stasiun trainer kit...");
        $startTime = microtime(true);

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $device = $devices->random();

            if ($forceAnomaly && $i % 3 === 0) {
                // Injeksi parameter anomali kritis
                $payload = [
                    'soilrs485' => [
                        'Ph' => round(mt_rand(38, 48) / 10, 1), // Masam kritis 3.8 - 4.8
                        'Ec' => mt_rand(2500, 3200), // Salinitas tinggi
                        'Humidity' => round(mt_rand(20, 35), 1), // Kering di bawah titik layu
                        'Temperature' => round(mt_rand(340, 380) / 10, 1), // Panas ekstrem
                        'Nitrogen' => mt_rand(5, 15),
                        'Phosporus' => mt_rand(3, 10),
                        'Kalium' => mt_rand(10, 30),
                    ],
                ];
            } else {
                // Parameter normal ideal SMK Pertanian
                $payload = [
                    'soilrs485' => [
                        'Ph' => round(mt_rand(62, 68) / 10, 1),
                        'Ec' => mt_rand(1200, 1650),
                        'Humidity' => round(mt_rand(600, 750) / 10, 1),
                        'Temperature' => round(mt_rand(230, 270) / 10, 1),
                        'Nitrogen' => mt_rand(45, 80),
                        'Phosporus' => mt_rand(25, 45),
                        'Kalium' => mt_rand(110, 160),
                    ],
                ];
            }

            $action->execute($device, $payload, provenance: 'simulation');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $elapsed = round(microtime(true) - $startTime, 3);
        $throughput = $elapsed > 0 ? round($count / $elapsed, 1) : $count;

        $this->info("Simulasi berhasil diselesaikan!");
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Paket Ingest', $count],
                ['Stasiun Terlibat', $devices->count()],
                ['Waktu Eksekusi', "{$elapsed} detik"],
                ['Throughput', "{$throughput} req/detik"],
                ['Status Database', 'PostgreSQL 17/18 — Berhasil Disimpan'],
            ]
        );

        return self::SUCCESS;
    }
}
