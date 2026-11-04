<?php

namespace App\Actions\Telemetry;

use App\Models\TelemetryReading;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTelemetryCsvAction
{
    /**
     * Stream database telemetry readings directly to CSV response.
     *
     * @param Builder<TelemetryReading> $query
     * @param string $filename
     * @return StreamedResponse
     */
    public function execute(Builder $query, string $filename = 'telemetry-history.csv'): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // CSV Columns Header
            fputcsv($handle, [
                'Waktu Pengukuran (WIB)',
                'Kode Perangkat',
                'Nama Meja / Stasiun',
                'pH Tanah',
                'EC Nutrisi (uS/cm)',
                'Kelembaban Tanah (%)',
                'Suhu Tanah (C)',
                'Nitrogen (mg/kg)',
                'Fosfor (mg/kg)',
                'Kalium (mg/kg)',
                'Status Peringatan',
            ]);

            // Chunk cursor to prevent memory spikes on large tables
            $query->with('device')->chunk(500, function ($readings) use ($handle) {
                foreach ($readings as $row) {
                    $flags = $row->threshold_flags ?? [];
                    $statusString = empty($flags) ? 'NORMAL' : implode('; ', (array) $flags);

                    fputcsv($handle, [
                        $row->recorded_at ? $row->recorded_at->format('Y-m-d H:i:s') : '-',
                        $row->device->code ?? 'DEV-' . $row->device_id,
                        $row->device->name ?? 'Meja Lab',
                        number_format((float) $row->ph, 2),
                        number_format((float) $row->ec, 0),
                        number_format((float) $row->humidity, 1),
                        number_format((float) $row->temperature, 1),
                        number_format((float) $row->nitrogen, 1),
                        number_format((float) $row->phosphorus, 1),
                        number_format((float) $row->potassium, 1),
                        $statusString,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
