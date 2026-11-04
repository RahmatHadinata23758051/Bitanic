<?php

namespace App\Services\Agronomy;

use App\Models\TelemetryReading;
use Illuminate\Support\Collection;

class AnomalyAlertEvaluator
{
    /**
     * Ideal parameter thresholds for vocational precision agriculture standard.
     */
    public const THRESHOLDS = [
        'ph' => ['min' => 5.5, 'max' => 7.2, 'ideal_min' => 6.0, 'ideal_max' => 6.8, 'unit' => 'pH'],
        'ec' => ['min' => 800, 'max' => 2400, 'ideal_min' => 1200, 'ideal_max' => 1800, 'unit' => 'µS/cm'],
        'humidity' => ['min' => 45.0, 'max' => 85.0, 'ideal_min' => 60.0, 'ideal_max' => 75.0, 'unit' => '%'],
        'temperature' => ['min' => 18.0, 'max' => 32.0, 'ideal_min' => 22.0, 'ideal_max' => 28.0, 'unit' => '°C'],
        'nitrogen' => ['min' => 20.0, 'max' => 120.0, 'ideal_min' => 40.0, 'ideal_max' => 80.0, 'unit' => 'mg/kg'],
        'phosphorus' => ['min' => 15.0, 'max' => 80.0, 'ideal_min' => 25.0, 'ideal_max' => 50.0, 'unit' => 'mg/kg'],
        'potassium' => ['min' => 50.0, 'max' => 250.0, 'ideal_min' => 100.0, 'ideal_max' => 180.0, 'unit' => 'mg/kg'],
    ];

    /**
     * Evaluate a reading and generate agronomic alerts with real remedial actions.
     *
     * @param TelemetryReading|null $reading
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluate(?TelemetryReading $reading): Collection
    {
        if (!$reading) {
            return collect();
        }

        $alerts = collect();

        // 1. Evaluasi pH Tanah
        $ph = (float) $reading->ph;
        if ($ph > 0) {
            if ($ph < 5.5) {
                $alerts->push([
                    'parameter' => 'pH Tanah',
                    'value' => number_format($ph, 1),
                    'unit' => 'pH',
                    'severity' => 'danger',
                    'title' => 'Tingkat Keasaman Masam Kritis (pH ' . number_format($ph, 1) . ')',
                    'impact' => 'Penyerapan unsur N-P-K terikat kuat oleh mineral tanah; memicu toksisitas ion Aluminium (Al).',
                    'action' => 'Aplikasikan 50-80 gram kapur dolomit per m² media tanam dan siram dengan air netral untuk aktivasi buffer.',
                ]);
            } elseif ($ph > 7.5) {
                $alerts->push([
                    'parameter' => 'pH Tanah',
                    'value' => number_format($ph, 1),
                    'unit' => 'pH',
                    'severity' => 'warning',
                    'title' => 'Tingkat Keasaman Bersifat Basa (pH ' . number_format($ph, 1) . ')',
                    'impact' => 'Risiko klorosis klorofil akibat defisiensi zat besi (Fe) dan mangan (Mn).',
                    'action' => 'Tambahkan belerang pertanian (sulfur) atau lakukan penyiraman dengan larutan pupuk ZA berkadar asam ringan.',
                ]);
            }
        }

        // 2. Evaluasi Daya Hantar Listrik / EC (Salinitas & Kepekatan Hara)
        $ec = (float) $reading->ec;
        if ($ec > 0) {
            if ($ec < 800) {
                $alerts->push([
                    'parameter' => 'EC Nutrisi',
                    'value' => number_format($ec, 0),
                    'unit' => 'µS/cm',
                    'severity' => 'warning',
                    'title' => 'Konsentrasi Larutan Rendah (EC ' . number_format($ec, 0) . ' µS/cm)',
                    'impact' => 'Tanaman kekurangan pasokan ion hara esensial untuk pembelahan sel vegetatif.',
                    'action' => 'Injeksi nutrisi AB Mix pekat 5-10 ml/liter air melalui relai solenoid fertigasi.',
                ]);
            } elseif ($ec > 2400) {
                $alerts->push([
                    'parameter' => 'EC Nutrisi',
                    'value' => number_format($ec, 0),
                    'unit' => 'µS/cm',
                    'severity' => 'danger',
                    'title' => 'Kepekatan Garam/Pupuk Berlebih (EC ' . number_format($ec, 0) . ' µS/cm)',
                    'impact' => 'Potensi plasmolisis akar (fertilizer burn), daun terbakar, dan tanaman layu.',
                    'action' => 'Segera lakukan flushing media tanam dengan air murni hingga EC air limpasan turun di bawah 1600 µS/cm.',
                ]);
            }
        }

        // 3. Evaluasi Lengas / Kelembaban Tanah
        $humidity = (float) $reading->humidity;
        if ($humidity > 0) {
            if ($humidity < 40.0) {
                $alerts->push([
                    'parameter' => 'Kelembaban Tanah',
                    'value' => number_format($humidity, 1),
                    'unit' => '%',
                    'severity' => 'danger',
                    'title' => 'Media Tanam Mengering di Bawah Titik Kritis (' . number_format($humidity, 1) . '%)',
                    'impact' => 'Tanaman mendekati titik layu permanen; stomata menutup dan fotosintesis terhenti.',
                    'action' => 'Aktifkan pompa irigasi tetes (Relay 1 ON) selama 3-5 menit sampai kelembaban mencapai 65%.',
                ]);
            } elseif ($humidity > 85.0) {
                $alerts->push([
                    'parameter' => 'Kelembaban Tanah',
                    'value' => number_format($humidity, 1),
                    'unit' => '%',
                    'severity' => 'warning',
                    'title' => 'Media Tanam Jenuh Air (' . number_format($humidity, 1) . '%)',
                    'impact' => 'Aerasi pori mikro tanah terhambat, risiko anoksia akar dan infeksi jamur busuk akar Pythium.',
                    'action' => 'Hentikan siklus penyiraman otomatis dan pastikan lubang drainase polybag tidak tersumbat.',
                ]);
            }
        }

        // 4. Evaluasi Suhu
        $temp = (float) $reading->temperature;
        if ($temp > 33.0) {
            $alerts->push([
                'parameter' => 'Suhu Lingkungan',
                'value' => number_format($temp, 1),
                'unit' => '°C',
                'severity' => 'warning',
                'title' => 'Suhu Kanopi Panas Ekstrem (' . number_format($temp, 1) . '°C)',
                'impact' => 'Tingkat transpirasi melampaui serapan akar; memicu heat stress pada pucuk daun muda.',
                'action' => 'Nyalakan misting fan (Relay 3 ON) atau tutup tirai paranet peneduh laboratorium.',
            ]);
        }

        return $alerts;
    }

    /**
     * Check if a single reading has any anomaly flags.
     */
    public function isAnomaly(TelemetryReading $reading): bool
    {
        return $this->evaluate($reading)->isNotEmpty();
    }
}
