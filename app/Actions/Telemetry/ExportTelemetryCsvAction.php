<?php

namespace App\Actions\Telemetry;

use App\Models\TelemetryReading;
use App\Services\Agronomy\AnomalyAlertEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTelemetryCsvAction
{
    protected int $maxRows = 50000;

    public function __construct(
        protected ?AnomalyAlertEvaluator $evaluator = null
    ) {
        $this->evaluator = $evaluator ?? app(AnomalyAlertEvaluator::class);
    }

    /**
     * Override max row limit (useful for testing or fine-grained policies).
     */
    public function setMaxRows(int $maxRows): self
    {
        $this->maxRows = $maxRows;

        return $this;
    }

    /**
     * Stream database telemetry readings directly to CSV response with memory-safe cursor and formula injection protection.
     *
     * @param  Builder<TelemetryReading>  $query
     */
    public function execute(
        Builder $query,
        string $filename = 'telemetry-history.csv',
        ?int $schoolId = null
    ): StreamedResponse {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query, $schoolId) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Columns Header with Provenance and Classification
            fputcsv($handle, [
                'Waktu Pengukuran (WIB)',
                'Kode Perangkat',
                'Nama Meja / Stasiun',
                'Asal Data',
                'pH Tanah',
                'EC Nutrisi (µS/cm)',
                'Kelembaban Tanah (%)',
                'Suhu Tanah (°C)',
                'Nitrogen (mg/kg)',
                'Fosfor (mg/kg)',
                'Kalium (mg/kg)',
                'Status Kondisi',
                'Detail Peringatan',
            ]);

            // Memory-safe PDO cursor streaming, bounded to explicit safety limit of 50,000 rows
            $maxRows = $this->maxRows;
            $rowCount = 0;
            $truncated = false;

            // Stream rows one by one directly from database cursor
            foreach ($query->with('device')->take($maxRows + 1)->cursor() as $row) {
                if ($rowCount >= $maxRows) {
                    $truncated = true;
                    break;
                }

                $deviceSchoolId = $row->device?->school_id ?? $schoolId;
                $classification = $this->evaluator->classify($row, $deviceSchoolId);
                $statusLabel = strtoupper($classification['label']);
                $deviationString = ! empty($classification['deviations'])
                    ? implode('; ', $classification['deviations'])
                    : '—';

                $timestampWib = $row->recorded_at
                    ? $row->recorded_at->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s')
                    : '—';

                fputcsv($handle, [
                    $timestampWib,
                    $this->sanitizeCsvCell($row->device?->code ?? 'DEV-'.$row->device_id),
                    $this->sanitizeCsvCell($row->device?->name ?? 'Meja Lab'),
                    $row->provenance ?? 'fixture',
                    $row->ph !== null ? number_format((float) $row->ph, 2, '.', '') : '—',
                    $row->ec !== null ? number_format((float) $row->ec, 0, '.', '') : '—',
                    $row->humidity !== null ? number_format((float) $row->humidity, 1, '.', '') : '—',
                    $row->temperature !== null ? number_format((float) $row->temperature, 1, '.', '') : '—',
                    $row->nitrogen !== null ? number_format((float) $row->nitrogen, 1, '.', '') : '—',
                    $row->phosphorus !== null ? number_format((float) $row->phosphorus, 1, '.', '') : '—',
                    $row->potassium !== null ? number_format((float) $row->potassium, 1, '.', '') : '—',
                    $statusLabel,
                    $this->sanitizeCsvCell($deviationString),
                ]);

                $rowCount++;
            }

            if ($truncated) {
                fputcsv($handle, [
                    '[CATATAN: Ekspor dibatasi maksimal 50.000 baris untuk keamanan memori server. Gunakan filter rentang tanggal atau stasiun untuk data lebih spesifik.]',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Sanitize cell against CSV Formula Injection (CWE-1236 / DDE attack).
     */
    protected function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        $str = (string) $value;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }
}
