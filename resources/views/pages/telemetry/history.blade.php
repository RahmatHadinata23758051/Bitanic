@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header Banner & Action Bar -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 text-xs font-bold uppercase rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono">
                    Log &amp; Analitik Telemetri
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">SMK Pertanian ATPH 4.0</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Riwayat Data Sensor &amp; Pusat Peringatan Anomali
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 max-w-3xl leading-relaxed">
                Log telemetri 7-in-1 waktu-nyata dari seluruh meja trainer kit praktik dengan deteksi deviasi otomatis dan rekomendasi tindakan agronomi.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-center min-w-[100px]">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Log</span>
                <span class="text-xl font-extrabold text-slate-900 font-tabular">{{ number_format($totalLogsCount) }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-center min-w-[100px]">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Meja Aktif</span>
                <span class="text-xl font-extrabold text-emerald-700 font-tabular">{{ $activeDevicesCount }} Kit</span>
            </div>
            <a
                href="{{ route('telemetry.history.export', request()->query()) }}"
                class="px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors flex items-center gap-2"
            >
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Ekspor CSV</span>
            </a>
        </div>
    </div>

    <!-- Anomaly Alert Hub (Hanya tampil jika ada anomali terdeteksi) -->
    @if($activeAlerts->isNotEmpty())
        <div class="p-5 rounded-2xl bg-amber-50/60 border border-amber-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-600 text-white flex items-center justify-center">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-amber-950">
                            Pusat Peringatan Deviasi Agronomi (Anomaly Alert Hub)
                        </h2>
                        <p class="text-[11px] text-amber-800">
                            Terdeteksi {{ $activeAlerts->count() }} parameter tanah di luar batas toleransi pertumbuhan ideal tanaman.
                        </p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-amber-200 text-amber-900">
                    Perlu Intervensi Lapangan
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($activeAlerts as $alert)
                    <div class="p-4 rounded-xl bg-white border {{ $alert['severity'] === 'danger' ? 'border-rose-200' : 'border-amber-200' }} shadow-xs space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider {{ $alert['severity'] === 'danger' ? 'text-rose-600' : 'text-amber-700' }}">
                                    {{ $alert['parameter'] }}
                                </span>
                                <h3 class="text-xs font-extrabold text-slate-900 mt-0.5">{{ $alert['title'] }}</h3>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $alert['severity'] === 'danger' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $alert['severity'] === 'danger' ? 'Bahaya' : 'Peringatan' }}
                            </span>
                        </div>

                        <p class="text-[11px] text-slate-600 leading-relaxed">
                            <strong class="text-slate-800 font-semibold">Dampak:</strong> {{ $alert['impact'] }}
                        </p>

                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-[11px] text-emerald-900 leading-relaxed">
                            <span class="font-bold text-emerald-800 flex items-center gap-1 mb-0.5">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600"></i>
                                <span>Tindakan Rekomendasi Siswa:</span>
                            </span>
                            {{ $alert['action'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Filter Toolbar -->
    <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('telemetry.history') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Stasiun Trainer Kit
                </label>
                <select
                    name="device_id"
                    class="w-full text-xs p-2 rounded-lg border border-slate-300 bg-white focus:border-emerald-600 outline-none transition-colors"
                >
                    <option value="">Semua Trainer Kit ({{ $devices->count() }})</option>
                    @foreach($devices as $dev)
                        <option value="{{ $dev->id }}" {{ $selectedDevice == $dev->id ? 'selected' : '' }}>
                            {{ $dev->code }} — {{ $dev->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Dari Tanggal
                </label>
                <input
                    type="date"
                    name="start_date"
                    value="{{ $startDate }}"
                    class="w-full text-xs p-2 rounded-lg border border-slate-300 bg-white focus:border-emerald-600 outline-none transition-colors font-mono"
                />
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Sampai Tanggal
                </label>
                <input
                    type="date"
                    name="end_date"
                    value="{{ $endDate }}"
                    class="w-full text-xs p-2 rounded-lg border border-slate-300 bg-white focus:border-emerald-600 outline-none transition-colors font-mono"
                />
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Status Kondisi
                </label>
                <select
                    name="status"
                    class="w-full text-xs p-2 rounded-lg border border-slate-300 bg-white focus:border-emerald-600 outline-none transition-colors"
                >
                    <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>Semua Data</option>
                    <option value="anomaly" {{ $selectedStatus === 'anomaly' ? 'selected' : '' }}>Hanya Anomali / Deviasi</option>
                    <option value="normal" {{ $selectedStatus === 'normal' ? 'selected' : '' }}>Hanya Normal (Ideal)</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-sm flex items-center justify-center gap-1.5"
                >
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Terapkan</span>
                </button>
                <a
                    href="{{ route('telemetry.history') }}"
                    class="py-2 px-3 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 text-xs font-semibold transition-colors"
                    title="Reset Filter"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table (Clean Industrial Agritech Table, Zero jQuery) -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                Menampilkan {{ $readings->firstItem() ?? 0 }} - {{ $readings->lastItem() ?? 0 }} dari {{ $readings->total() }} Data Telemetri
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-mono text-[11px] uppercase">
                        <th class="py-3 px-3">Waktu (WIB)</th>
                        <th class="py-3 px-3">Trainer Kit</th>
                        <th class="py-3 px-3 text-right">pH</th>
                        <th class="py-3 px-3 text-right">EC (<span class="normal-case">µS/cm</span>)</th>
                        <th class="py-3 px-3 text-right">Kelembaban</th>
                        <th class="py-3 px-3 text-right">Suhu</th>
                        <th class="py-3 px-3 text-right">N-P-K (<span class="normal-case">mg/kg</span>)</th>
                        <th class="py-3 px-3 text-center">Asal Data</th>
                        <th class="py-3 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($readings as $row)
                        @php
                            $ph = $row->ph !== null ? (float) $row->ph : null;
                            $ec = $row->ec !== null ? (float) $row->ec : null;
                            $hum = $row->humidity !== null ? (float) $row->humidity : null;
                            $temp = $row->temperature !== null ? (float) $row->temperature : null;
                            $n = $row->nitrogen !== null ? (float) $row->nitrogen : null;
                            $p = $row->phosphorus !== null ? (float) $row->phosphorus : null;
                            $k = $row->potassium !== null ? (float) $row->potassium : null;

                            $isPhAnomaly = $ph !== null && ($ph < 5.5 || $ph > 7.5);
                            $isEcAnomaly = $ec !== null && ($ec < 800 || $ec > 2400);
                            $isHumAnomaly = $hum !== null && ($hum < 40.0 || $hum > 85.0);
                            $isTempAnomaly = $temp !== null && $temp > 33.0;

                            $hasAnomaly = $isPhAnomaly || $isEcAnomaly || $isHumAnomaly || $isTempAnomaly || !empty($row->threshold_flags);
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors {{ $hasAnomaly ? 'bg-amber-50/20' : '' }}">
                            <!-- Waktu -->
                            <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">
                                {{ $row->recorded_at ? $row->recorded_at->format('Y-m-d H:i:s') : '—' }}
                            </td>

                            <!-- Perangkat -->
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <span class="font-bold text-slate-800 block text-xs">{{ $row->device->name ?? 'Meja Lab' }}</span>
                                <span class="font-mono text-[10px] text-slate-400 block">{{ $row->device->code ?? 'DEV-'.$row->device_id }}</span>
                            </td>

                            <!-- pH -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                @if($ph !== null)
                                    <span class="font-tabular text-xs font-bold {{ $isPhAnomaly ? 'text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded' : 'text-slate-800' }}">
                                        {{ number_format($ph, 1) }}
                                    </span>
                                @else
                                    <span class="font-tabular text-xs text-slate-300">—</span>
                                @endif
                            </td>

                            <!-- EC -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                @if($ec !== null)
                                    <span class="font-tabular text-xs font-bold {{ $isEcAnomaly ? 'text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded' : 'text-slate-800' }}">
                                        {{ number_format($ec, 0) }}
                                    </span>
                                @else
                                    <span class="font-tabular text-xs text-slate-300">—</span>
                                @endif
                            </td>

                            <!-- Kelembaban -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                @if($hum !== null)
                                    <span class="font-tabular text-xs font-bold {{ $isHumAnomaly ? 'text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded' : 'text-slate-800' }}">
                                        {{ number_format($hum, 1) }}%
                                    </span>
                                @else
                                    <span class="font-tabular text-xs text-slate-300">—</span>
                                @endif
                            </td>

                            <!-- Suhu -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                @if($temp !== null)
                                    <span class="font-tabular text-xs font-bold {{ $isTempAnomaly ? 'text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded' : 'text-slate-800' }}">
                                        {{ number_format($temp, 1) }}°C
                                    </span>
                                @else
                                    <span class="font-tabular text-xs text-slate-300">—</span>
                                @endif
                            </td>

                            <!-- NPK -->
                            <td class="py-2.5 px-3 text-right font-tabular text-xs text-slate-700 whitespace-nowrap">
                                <span class="text-slate-900 font-semibold">{{ $n !== null ? number_format($n, 0) : '—' }}</span>
                                <span class="text-slate-300">/</span>
                                <span class="text-slate-900 font-semibold">{{ $p !== null ? number_format($p, 0) : '—' }}</span>
                                <span class="text-slate-300">/</span>
                                <span class="text-slate-900 font-semibold">{{ $k !== null ? number_format($k, 0) : '—' }}</span>
                            </td>

                            <!-- Provenance Badge -->
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $row->provenance === 'hardware' ? 'bg-emerald-100 text-emerald-800' : ($row->provenance === 'simulation' ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-slate-200 text-slate-700') }}">
                                    {{ $row->provenance === 'hardware' ? 'Hardware' : ($row->provenance === 'simulation' ? 'Simulasi' : 'Fixture') }}
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                @if($hasAnomaly)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        ANOMALI
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        NORMAL
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-10 text-center text-slate-400">
                                <i data-lucide="database" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-xs font-semibold">Tidak ada log telemetri yang sesuai kriteria filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($readings->hasPages())
            <div class="pt-4 border-t border-slate-100">
                {{ $readings->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
